<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Organizations\Pages;

use App\Modules\Admin\Filament\Resources\Organizations\OrganizationResource;
use App\Modules\Admin\Filament\Resources\Subscriptions\GrantSubscriptionAction;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Admin\Filament\Support\ModuleAction;
use App\Modules\Admin\Support\AdminActor;
use App\Modules\Identity\Actions\ResendOwnerVerificationCode;
use App\Modules\Identity\Actions\SuspendOrganization;
use App\Modules\Identity\Actions\UnsuspendOrganization;
use App\Modules\Identity\Actions\UpdateOrganizationFeatures;
use App\Modules\Identity\Actions\VerifyOrganization;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * An organization with the §16 admin actions, each through the Identity (or Billing) Action with
 * `Actor::forAdmin()`. In release scope `core` the features form has the auction switch only and
 * saves only that switch (RELEASE_SCOPE.md §11).
 */
final class ViewOrganization extends ViewRecord
{
    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verify')
                ->label(Lang::get('resources.organizations.actions.verify'))
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->visible(fn (): bool => $this->isOpen() && $this->organization()->verified_at === null)
                ->requiresConfirmation()
                ->action(fn () => $this->verify(true)),
            Action::make('unverify')
                ->label(Lang::get('resources.organizations.actions.unverify'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('gray')
                ->visible(fn (): bool => $this->isOpen() && $this->organization()->verified_at !== null)
                ->requiresConfirmation()
                ->action(fn () => $this->verify(false)),
            Action::make('suspend')
                ->label(Lang::get('resources.organizations.actions.suspend'))
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->visible(fn (): bool => $this->organization()->status === OrganizationStatus::Active)
                ->schema([
                    Textarea::make('reason')->label(Lang::get('fields.reason'))->required()->minLength(3)->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    ModuleAction::run(
                        fn () => app(SuspendOrganization::class)->handle($this->organization(), trim((string) $data['reason']), AdminActor::current()),
                        Lang::get('resources.organizations.notifications.suspended'),
                    );
                    $this->refreshRecord();
                }),
            Action::make('unsuspend')
                ->label(Lang::get('resources.organizations.actions.unsuspend'))
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('success')
                ->visible(fn (): bool => $this->organization()->status === OrganizationStatus::Suspended)
                ->requiresConfirmation()
                ->action(function (): void {
                    ModuleAction::run(
                        fn () => app(UnsuspendOrganization::class)->handle($this->organization(), AdminActor::current()),
                        Lang::get('resources.organizations.notifications.unsuspended'),
                    );
                    $this->refreshRecord();
                }),
            ActionGroup::make([
                Action::make('features')
                    ->label(Lang::get('resources.organizations.actions.features'))
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->visible(fn (): bool => $this->isOpen())
                    ->fillForm(fn (): array => [
                        'api_enabled' => $this->organization()->api_enabled,
                        'auction_enabled' => $this->organization()->auction_enabled,
                        'sponsorship_enabled' => $this->organization()->sponsorship_enabled,
                    ])
                    ->schema([
                        Toggle::make('api_enabled')->label(Lang::get('fields.api_enabled'))
                            ->visible(static fn (): bool => OrganizationResource::showsAdvancedFeatures()),
                        Toggle::make('auction_enabled')->label(Lang::get('fields.auction_enabled')),
                        Toggle::make('sponsorship_enabled')->label(Lang::get('fields.sponsorship_enabled'))
                            ->visible(static fn (): bool => OrganizationResource::showsAdvancedFeatures()),
                    ])
                    ->action(function (array $data): void {
                        $features = ['auction_enabled' => (bool) $data['auction_enabled']];

                        if (OrganizationResource::showsAdvancedFeatures()) {
                            $features['api_enabled'] = (bool) ($data['api_enabled'] ?? false);
                            $features['sponsorship_enabled'] = (bool) ($data['sponsorship_enabled'] ?? false);
                        }

                        ModuleAction::run(
                            fn () => app(UpdateOrganizationFeatures::class)->handle($this->organization(), $features, AdminActor::current()),
                            Lang::get('notifications.saved'),
                        );
                        $this->refreshRecord();
                    }),
                Action::make('resendVerification')
                    ->label(Lang::get('resources.organizations.actions.resend_verification'))
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->visible(fn (): bool => $this->isOpen() && $this->ownerIsUnverified())
                    ->requiresConfirmation()
                    ->action(function (): void {
                        $otp = ModuleAction::run(
                            fn () => app(ResendOwnerVerificationCode::class)->handle($this->organization(), AdminActor::current()),
                        );

                        Notification::make()
                            ->title(Lang::get($otp !== null
                                ? 'resources.organizations.notifications.verification_sent'
                                : 'resources.organizations.notifications.already_verified'))
                            ->success()
                            ->send();
                    }),
                GrantSubscriptionAction::make($this->organization())
                    ->visible(fn (): bool => $this->organization()->status === OrganizationStatus::Active)
                    ->after(fn () => $this->refreshRecord()),
            ])->label(Lang::get('common.more'))->button()->color('gray'),
        ];
    }

    private function organization(): Organization
    {
        /** @var Organization $organization */
        $organization = $this->getRecord();

        return $organization;
    }

    private function isOpen(): bool
    {
        return $this->organization()->status !== OrganizationStatus::Deleted;
    }

    private function ownerIsUnverified(): bool
    {
        $owner = $this->organization()->memberships()
            ->where('role', OrgRole::Owner->value)
            ->with('user')
            ->first()
            ?->user;

        return $owner !== null && ! $owner->hasVerifiedEmail();
    }

    private function verify(bool $verified): void
    {
        ModuleAction::run(
            fn () => app(VerifyOrganization::class)->handle($this->organization(), $verified, AdminActor::current()),
            Lang::get($verified ? 'resources.organizations.notifications.verified' : 'resources.organizations.notifications.unverified'),
        );

        $this->refreshRecord();
    }

    private function refreshRecord(): void
    {
        $this->getRecord()->refresh();
    }
}
