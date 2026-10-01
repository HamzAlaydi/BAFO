<?php

declare(strict_types=1);

use App\Modules\Bidding\Enums\ReportStatus;
use App\Modules\Bidding\Events\AwardIssued;
use App\Modules\Bidding\Jobs\GenerateCompetitionReport;
use App\Modules\Bidding\Listeners\QueueCompetitionReport;
use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\CompetitionReport;
use App\Modules\Bidding\Services\LiveStateManager;
use App\Modules\Bidding\Services\ReportGenerator;
use App\Modules\Competitions\Actions\CloseDueCompetition;
use App\Modules\Competitions\Database\Factories\CompetitionFactory;
use App\Modules\Competitions\Events\CompetitionClosed;
use App\Support\Auth\Actor;
use App\Support\Files\File;
use App\Support\Files\FilePurpose;
use App\Support\Pdf\PdfRenderer;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Bidding\Scenario;

/*
 * The result report (ARCHITECTURE §7.14): GET …/report, the generation job, the regeneration
 * triggers and the file access rule of §8.5.
 */

beforeEach(function () {
    Storage::fake('private');
    Queue::fake();
});

function closedForReport(object $test): Scenario
{
    $s = Scenario::make($test, fn (CompetitionFactory $f) => $f->withoutFinalWindow()->live(), 2, ['start_price_minor' => 10_000_000]);
    $s->offer(0, 9_600_000)->assertCreated();
    $s->offer(1, 9_500_000)->assertCreated();

    $test->travelTo($s->competition->effective_close_at->addSecond());
    app(CloseDueCompetition::class)->handle($s->competition->id);
    $s->refresh();

    return $s;
}

it('is not available before the first close', function () {
    $s = Scenario::make($this, attributes: ['start_price_minor' => 10_000_000]);
    $s->actingAs($s->issuer);

    $this->getJson($s->url('report'))->assertStatus(409)->assertJsonPath('code', 'report_not_available');
});

it('queues the report, then serves the ready file', function () {
    $s = closedForReport($this);
    $s->actingAs($s->issuer);

    $this->getJson($s->url('report'))
        ->assertStatus(202)
        ->assertJsonPath('data', ['status' => 'pending', 'locale' => 'ar', 'generated_at' => null, 'file' => null]);

    Queue::assertPushed(GenerateCompetitionReport::class, fn (GenerateCompetitionReport $job) => $job->locale === 'ar' && $job->queue === 'pdf');

    app()->call([new GenerateCompetitionReport($s->competition->id, 'ar'), 'handle']);

    $response = $this->getJson($s->url('report'))
        ->assertOk()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.locale', 'ar')
        ->assertJsonStructure(['data' => ['generated_at', 'file' => ['id', 'name', 'mime_type', 'extension', 'size_bytes', 'download_path', 'created_at']]]);

    $file = File::query()->where('public_id', $response->json('data.file.id'))->sole();

    expect($file->purpose)->toBe(FilePurpose::CompetitionReport)
        ->and($file->organization_id)->toBe($s->issuerOrganization->id)
        ->and(Storage::disk('private')->get($file->path))->toStartWith('%PDF');

    // The issuer team downloads it; a participant cannot.
    $this->get($response->json('data.file.download_path'))->assertOk();

    Auth::forgetGuards();
    $s->actingAs($s->bidder(0));
    $this->getJson($response->json('data.file.download_path'))->assertForbidden();
});

it('renders per locale and regenerates a stale report', function () {
    $s = closedForReport($this);
    $s->actingAs($s->issuer);

    $this->getJson($s->url('report?locale=en'))->assertStatus(202)->assertJsonPath('data.locale', 'en');
    app()->call([new GenerateCompetitionReport($s->competition->id, 'en'), 'handle']);
    $this->getJson($s->url('report?locale=en'))->assertOk();

    // A later change of the live state makes it stale.
    app(LiveStateManager::class)->bumpVersion($s->competition->id);
    $this->getJson($s->url('report?locale=en'))->assertStatus(202)->assertJsonPath('data.status', 'pending');

    $this->getJson($s->url('report?locale=fr'))->assertUnprocessable()->assertJsonValidationErrors(['locale']);
});

it('replaces the previous file when it regenerates', function () {
    $s = closedForReport($this);

    $first = app(ReportGenerator::class)->generate($s->competition, 'ar');
    $oldFile = $first->file_id;
    app(LiveStateManager::class)->bumpVersion($s->competition->id);
    $second = app(ReportGenerator::class)->generate($s->competition, 'ar');

    expect($second->id)->toBe($first->id)
        ->and($second->file_id)->not->toBe($oldFile)
        ->and(File::query()->find($oldFile))->toBeNull()
        ->and(CompetitionReport::query()->count())->toBe(1);
});

it('contains every section of the report in both languages', function (string $locale, string $title) {
    $s = closedForReport($this);
    $data = app(ReportGenerator::class)->data($s->competition, $locale);
    app()->setLocale($locale); // PdfRenderer renders the view in the report locale
    $html = view(ReportGenerator::VIEW, [...$data, 'locale' => $locale, 'direction' => $locale === 'ar' ? 'rtl' : 'ltr'])->render();

    expect($html)->toContain($title)
        ->toContain($s->competition->reference_no)
        ->toContain((string) $data['ledgerHeadHash'])
        ->and($data['ranking'][0]['rank'])->toBe(1)
        ->and($data['ranking'][0]['name'])->toBe($s->participant(1)->organization->name)
        ->and($data['offers'])->toHaveCount(2)
        ->and($data['rules'])->not->toBeEmpty()
        ->and(collect($data['timeline'])->pluck('label')->all())->toContain(__('bidding.report.timeline.closed', [], $locale));
})->with([
    'arabic' => ['ar', 'تقرير نتائج المنافسة'],
    'english' => ['en', 'Competition result report'],
]);

it('marks the report failed when rendering fails, without failing the job', function () {
    $s = closedForReport($this);
    app()->instance(PdfRenderer::class, new class implements PdfRenderer
    {
        public function render(string $view, array $data, string $locale): string
        {
            throw new RuntimeException('renderer down');
        }
    });

    app()->call([new GenerateCompetitionReport($s->competition->id, 'ar'), 'handle']);

    expect(CompetitionReport::query()->sole()->status)->toBe(ReportStatus::Failed);
});

it('regenerates at close in the creator locale and after an award in every rendered locale', function () {
    $s = closedForReport($this);
    CompetitionReport::query()->create(['competition_id' => $s->competition->id, 'locale' => 'en', 'status' => ReportStatus::Ready, 'live_version' => 1]);

    (new QueueCompetitionReport)->handle(new CompetitionClosed($s->competition));

    Queue::assertPushed(GenerateCompetitionReport::class, fn (GenerateCompetitionReport $job) => $job->locale === 'ar');
    Queue::assertPushed(GenerateCompetitionReport::class, fn (GenerateCompetitionReport $job) => $job->locale === 'en');

    // While a generation is queued, another dispatch for the same locale is dropped (the job
    // renders the state current when it runs). Once processed, the award queues both again.
    (new QueueCompetitionReport)->handle(new CompetitionClosed($s->competition));
    Queue::assertPushed(GenerateCompetitionReport::class, 2);

    foreach (['ar', 'en'] as $locale) {
        (new UniqueLock(Cache::store()))->release(new GenerateCompetitionReport($s->competition->id, $locale));
    }

    $award = Award::factory()->create(['competition_id' => $s->competition->id, 'participant_id' => $s->participant(1)->id]);
    (new QueueCompetitionReport)->handle(new AwardIssued($award, $s->competition, Actor::system()));

    Queue::assertPushed(GenerateCompetitionReport::class, 4);
});
