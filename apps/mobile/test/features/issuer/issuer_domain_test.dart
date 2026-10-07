import 'package:bafo/core/models/rules.dart';
import 'package:bafo/features/issuer/domain/competition_draft_form.dart';
import 'package:bafo/features/issuer/domain/issuer_checks.dart';
import 'package:bafo/features/issuer/domain/staged_invitee.dart';
import 'package:bafo/features/issuer/presentation/create/draft_form_fields.dart';
import 'package:bafo/features/issuer/presentation/edit/edit_competition_cubit.dart';
import 'package:bafo/features/issuer/presentation/widgets/issuer_widgets.dart';
import 'package:flutter_test/flutter_test.dart';

import 'issuer_test_helpers.dart';

void main() {
  final lookups = fixtureLookups();
  final now = DateTime.utc(2026, 10, 1, 9);
  final tenderPreset = lookups.presets.firstWhere(
    (preset) => preset.code == 'standard_live_tender',
  );
  final office = lookups.categories.firstWhere(
    (c) => c.code == 'office_supplies',
  );
  final vehicles = lookups.categories.firstWhere((c) => c.code == 'vehicles');
  final other = lookups.categories.firstWhere((c) => c.isOther);
  final riyadh = lookups.regions.first;

  CompetitionDraftForm complete() => CompetitionDraftForm(
    direction: Direction.tender,
    format: CompetitionFormat.live,
    presetCode: tenderPreset.code,
    title: '  توريد أجهزة  ',
    description: 'نطاق العمل',
    categoryId: office.id,
    regionId: riyadh.id,
    startPriceMinor: 25000000,
    reservePriceMinor: 22000000,
    scheduledCloseAt: now.add(const Duration(days: 7)),
  );

  group('CompetitionDraftForm', () {
    test('a complete form has no problems on any step', () {
      expect(
        complete().validate(CreateStep.review, lookups: lookups, now: now),
        isEmpty,
      );
    });

    test('step 1 needs a direction, a format and a matching preset', () {
      const form = CompetitionDraftForm();
      // The preset is asked only once the direction and format exist: its
      // cards are not shown before (RELEASE_SCOPE.md §2.6).
      expect(form.validate(CreateStep.type, lookups: lookups, now: now), {
        DraftField.direction: DraftProblem.required,
        DraftField.format: DraftProblem.required,
      });
      const chosen = CompetitionDraftForm(
        direction: Direction.tender,
        format: CompetitionFormat.live,
      );
      expect(chosen.validate(CreateStep.type, lookups: lookups, now: now), {
        DraftField.preset: DraftProblem.required,
      });
      // A preset of the other direction does not count.
      final mismatched = complete().copyWith(direction: Direction.auction);
      expect(mismatched.validate(CreateStep.type, lookups: lookups, now: now), {
        DraftField.preset: DraftProblem.required,
      });
    });

    test('basics: title, category (auction allowed, other text), region', () {
      final form = complete().copyWith(
        title: ' ',
        direction: Direction.auction,
        categoryId: () => vehicles.id,
        regionId: () => null,
      );
      expect(form.validate(CreateStep.basics, lookups: lookups, now: now), {
        DraftField.title: DraftProblem.required,
        DraftField.category: DraftProblem.auctionNotAllowed,
        DraftField.region: DraftProblem.required,
      });
      final otherCategory = complete().copyWith(categoryId: () => other.id);
      expect(
        otherCategory.validate(CreateStep.basics, lookups: lookups, now: now),
        {DraftField.categoryOtherText: DraftProblem.required},
      );
    });

    test('prices: text that cannot be used blocks the step (FQ2, FQ8)', () {
      final tender = complete().copyWith(startPriceMinor: () => null);
      CompetitionDraftForm typed(String text) => withAmountText(
        tender,
        DraftField.startPrice,
        text,
        granularityMinor: 100,
      );
      for (final (text, problem) in [
        ('1.234', DraftProblem.amountInvalid),
        ('1.2.3', DraftProblem.amountInvalid),
        ('0', DraftProblem.amountNotPositive),
        ('1500.50', DraftProblem.amountWholeRiyals),
      ]) {
        expect(
          typed(text).validate(CreateStep.schedule, lookups: lookups, now: now),
          {DraftField.startPrice: problem},
          reason: text,
        );
        expect(
          typed(text).validate(CreateStep.review, lookups: lookups, now: now),
          containsPair(DraftField.startPrice, problem),
          reason: text,
        );
      }
      // Usable or empty text clears the mark; the autosave never stores it.
      final marked = typed('1.234');
      for (final text in ['1500', '']) {
        final cleared = withAmountText(
          marked,
          DraftField.startPrice,
          text,
          granularityMinor: 100,
        );
        expect(cleared.unreadableAmounts, isEmpty, reason: text);
      }
      expect(marked.toJson().containsKey('unreadable_amounts'), isFalse);
      expect(marked, isNot(tender));
    });

    test('prices: the auction start price is required, R6 by direction', () {
      final auction = complete().copyWith(
        direction: Direction.auction,
        startPriceMinor: () => null,
      );
      expect(
        auction.validate(CreateStep.schedule, lookups: lookups, now: now),
        {DraftField.startPrice: DraftProblem.required},
      );
      // Tender: reserve above the ceiling.
      final tender = complete().copyWith(reservePriceMinor: () => 30000000);
      expect(tender.validate(CreateStep.schedule, lookups: lookups, now: now), {
        DraftField.reservePrice: DraftProblem.reserveVersusStart,
      });
      // Auction: the same numbers are fine (reserve ≥ opening price).
      final ok = complete().copyWith(
        direction: Direction.auction,
        reservePriceMinor: () => 30000000,
      );
      expect(
        ok.validate(CreateStep.schedule, lookups: lookups, now: now),
        isEmpty,
      );
    });

    test(
      'schedule: past opening, close before opening, too short, too long',
      () {
        final past = complete().copyWith(
          opensOnPublish: false,
          biddingOpensAt: () => now.subtract(const Duration(minutes: 1)),
        );
        expect(
          past.validate(
            CreateStep.schedule,
            lookups: lookups,
            now: now,
          )[DraftField.opensAt],
          DraftProblem.opensInPast,
        );
        final before = complete().copyWith(scheduledCloseAt: () => now);
        expect(
          before.validate(
            CreateStep.schedule,
            lookups: lookups,
            now: now,
          )[DraftField.closesAt],
          DraftProblem.closeBeforeOpen,
        );
        final short = complete().copyWith(
          scheduledCloseAt: () => now.add(const Duration(minutes: 5)),
        );
        expect(
          short.validate(
            CreateStep.schedule,
            lookups: lookups,
            now: now,
          )[DraftField.closesAt],
          DraftProblem.tooShort,
        );
        final long = complete().copyWith(
          scheduledCloseAt: () => now.add(const Duration(days: 91)),
        );
        expect(
          long.validate(
            CreateStep.schedule,
            lookups: lookups,
            now: now,
          )[DraftField.closesAt],
          DraftProblem.durationTooLong,
        );
      },
    );

    test('toInput sends the preset rules with the prices (G4)', () {
      final input = complete().toInput(tenderPreset, lookups);
      final json = input.toJson();
      expect(json['title'], 'توريد أجهزة');
      expect(json['preset_code'], 'standard_live_tender');
      expect(json['bidding_opens_at'], isNull);
      final rules = json['rules']! as Map<String, dynamic>;
      expect(rules['start_price_minor'], 25000000);
      expect(rules['reserve_price_minor'], 22000000);
      expect(rules['min_step_bps'], 50);
      expect(rules['auto_extend'], isA<Map<String, dynamic>>());
      expect(json['category_other_text'], isNull);
    });

    test('server field paths map to fields and steps', () {
      expect(
        DraftField.fromServerPath('rules.start_price_minor'),
        DraftField.startPrice,
      );
      expect(
        DraftField.fromServerPath('scheduled_close_at')!.step,
        CreateStep.schedule,
      );
      expect(DraftField.fromServerPath('category_id')!.step, CreateStep.basics);
      expect(DraftField.fromServerPath('rules.min_step_bps'), isNull);
    });
  });

  test('splitServerErrors binds fields and keeps the rest for the alert', () {
    final both = splitServerErrors(
      apiError(
        'validation_failed',
        status: 422,
        fieldErrors: {
          'title': ['مطلوب'],
          'rules.min_step_bps': ['خارج النطاق'],
        },
      ),
    );
    expect(both.fields, {DraftField.title: 'مطلوب'});
    expect(both.rest?.fieldErrors.keys, ['rules.min_step_bps']);
    final onlyFields = splitServerErrors(
      apiError(
        'validation_failed',
        fieldErrors: {
          'region_id': ['x'],
        },
      ),
    );
    expect(onlyFields.rest, isNull);
    final business = apiError('issuer_plan_required', status: 403);
    expect(splitServerErrors(business).rest, business);
  });

  group('SchedulePreview (G3)', () {
    test('derives the final window, cutoff and latest close of §7.2', () {
      final close = DateTime.utc(2026, 10, 9, 17);
      final preview = SchedulePreview.of(
        opensAt: DateTime.utc(2026, 10, 2, 17),
        closesAt: close,
        rules: tenderPreset.rules,
        publishAt: now,
      );
      expect(
        preview.finalWindowStartsAt,
        close.subtract(const Duration(minutes: 60)),
      );
      expect(preview.invitationCutoffAt, preview.finalWindowStartsAt);
      expect(preview.hardStopAt, close.add(const Duration(seconds: 180 * 10)));
    });

    test('without a final window the cutoff is 60 minutes before, never before opening', () {
      final close = now.add(const Duration(minutes: 30));
      final preview = SchedulePreview.of(
        opensAt: null,
        closesAt: close,
        rules: const Rules(),
        publishAt: now,
      );
      expect(preview.finalWindowStartsAt, isNull);
      expect(preview.invitationCutoffAt, now);
      expect(preview.hardStopAt, isNull);
    });
  });

  group('setupChecklist', () {
    test('the draft fixture is complete (description, schedule, 2 of 2 invitations)', () {
      final checks = setupChecklist(
        issuerCompetition('competition_issuer_draft'),
      );
      expect(checks.every((check) => check.done), isTrue);
      expect(
        checks.map((check) => check.item),
        isNot(contains(SetupItem.startPrice)),
      );
    });

    test('missing items are reported', () {
      final competition = issuerCompetition('competition_issuer_draft', (json) {
        json['description'] = '  ';
        json['direction'] = 'auction';
        (json['rules'] as Map<String, dynamic>)['start_price_minor'] = null;
        (json['counts'] as Map<String, dynamic>)['invitations'] = 1;
      });
      final open = setupChecklist(competition)
          .where((check) => !check.done)
          .map((check) => check.item);
      expect(open, [
        SetupItem.description,
        SetupItem.startPrice,
        SetupItem.invitations,
      ]);
    });
  });

  group('IssuerSections', () {
    test('drafts have no live sections; closed ones have the award view', () {
      final draft = issuerCompetition('competition_issuer_draft');
      expect(draft.hasLiveSections, isFalse);
      expect(draft.acceptsAttachments, isTrue);
      expect(draft.allowsAttachmentRemoval, isTrue);
      final closed = issuerCompetition('competition_issuer_closed');
      expect(closed.hasAwardSection, isTrue);
      expect(closed.hasWebOnlyActions, isTrue);
      final live = issuerCompetition('competition_issuer_live_initial');
      expect(live.acceptsAttachments, isTrue);
      expect(live.allowsAttachmentRemoval, isFalse);
    });
  });

  group('EditScope', () {
    test('fields by status (API.md §1.4 PATCH)', () {
      expect(
        EditScope.of(issuerCompetition('competition_issuer_draft')),
        EditScope.draft,
      );
      expect(
        EditScope.of(issuerCompetition('competition_issuer_scheduled')),
        EditScope.scheduled,
      );
      expect(
        EditScope.of(issuerCompetition('competition_issuer_live_initial')),
        EditScope.live,
      );
      expect(
        EditScope.of(issuerCompetition('competition_issuer_closed')),
        EditScope.none,
      );
      expect(EditScope.scheduled.allows(DraftField.startPrice), isFalse);
      expect(EditScope.scheduled.allows(DraftField.closesAt), isTrue);
      expect(EditScope.live.allows(DraftField.region), isFalse);
      expect(EditScope.live.allows(DraftField.description), isTrue);
    });

    test('a draft PATCH carries the full rules with the preset code', () {
      final competition = issuerCompetition('competition_issuer_draft');
      final form = EditCompetitionCubit.formOf(competition)
          .copyWith(title: 'جديد', startPriceMinor: () => 26000000);
      final body = EditCompetitionCubit.patchBody(
        competition,
        form,
        EditScope.draft,
        fixtureLookups(),
      );
      expect(body['title'], 'جديد');
      expect(body['preset_code'], 'standard_live_tender');
      final rules = body['rules']! as Map<String, dynamic>;
      expect(rules['start_price_minor'], 26000000);
      expect(rules['reserve_price_minor'], 22000000);
      final live = EditCompetitionCubit.patchBody(
        competition,
        form,
        EditScope.live,
        fixtureLookups(),
      );
      expect(live.keys, unorderedEquals(['title', 'description']));
    });
  });

  group('invitations', () {
    test(
      'splitEmails splits, de-duplicates and returns the invalid pieces',
      () {
        final (:valid, :invalid) = splitEmails(
          'a@x.sa, b@y.sa;A@X.sa\nnot-an-email  c@z.sa،d',
        );
        expect(valid, ['a@x.sa', 'b@y.sa', 'c@z.sa']);
        expect(invalid, ['not-an-email', 'd']);
      },
    );

    test('invitationRowErrors reads field errors and item codes by row', () {
      final errors = invitationRowErrors(
        fieldErrors: {
          'invitations.1.email': ['مدعو من قبل'],
          'invitations': ['x'],
        },
        details: {
          'item_codes': {
            'invitations.1.email': 'invitation_duplicate',
            'invitations.3.vendor_id': 'vendor_blocked',
          },
        },
      );
      expect(errors[1], (code: 'invitation_duplicate', message: 'مدعو من قبل'));
      expect(errors[3], (code: 'vendor_blocked', message: ''));
      expect(errors.containsKey(0), isFalse);
    });

    test('a staged row sends sponsored only when fees are covered', () {
      final row = StagedInvitee.email(' a@x.sa ').withSponsored(true);
      expect(row.key, 'email:a@x.sa');
      expect(row.toInput().toJson(), {'email': 'a@x.sa', 'sponsored': true});
      expect(row.toInput(coverFees: false).toJson()['sponsored'], isFalse);
    });
  });

  group('formatting', () {
    test('formatSignedBps', () {
      expect(formatSignedBps(6080), '+60.8%');
      expect(formatSignedBps(50), '+0.5%');
      expect(formatSignedBps(-1090), '−10.9%');
      expect(formatSignedBps(0), '0%');
      expect(formatSignedBps(10000), '+100%');
    });

    test('minorToInput', () {
      expect(minorToInput(25000000), '250000');
      expect(minorToInput(12550), '125.50');
      expect(minorToInput(5), '0.05');
      expect(minorToInput(null), '');
    });
  });
}
