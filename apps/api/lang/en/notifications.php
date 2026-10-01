<?php

declare(strict_types=1);

/*
| Notifications module (ARCHITECTURE §11.4). Keys and placeholders are binding; this file has
| the same keys as the Arabic one. Each type's templates live under the type with "." → "_".
|
| Placeholders: :competition_type ("tender" or "auction", lowercase inside sentences), times in
| Asia/Riyadh (:close_time, :cutoff_time, :ends_at) and amounts (:amount). Plural rows are
| rendered with trans_choice on the named count.
*/

return [

    // ---------------------------------------------------------------- catalogue (§11.3)

    'competition_invited' => [
        'title' => 'New :competition_type invitation',
        'body' => ':issuer_name invites you to take part in “:competition_title”.',
        'body_sponsored_suffix' => ' Participation fees are covered.',
    ],

    'competition_updated' => [
        'title' => 'Competition updated',
        'body' => 'The issuer updated “:competition_title”.',
    ],

    'competition_opened' => [
        'title' => 'Offers are open',
        'body' => 'Offers are now open for “:competition_title”.',
    ],

    'competition_final_window_started' => [
        'title' => 'Final pricing window started',
        'body' => 'The final pricing window of “:competition_title” has started. It closes at :close_time.',
    ],

    'competition_closing_soon' => [
        'title' => 'Closing soon',
        'body' => '{1} “:competition_title” closes in 1 minute.|[2,*] “:competition_title” closes in :minutes minutes.',
    ],

    'competition_extended' => [
        'title' => 'Closing time extended',
        'body' => 'The closing time of “:competition_title” was extended to :close_time.',
    ],

    'competition_closed' => [
        'title' => 'Competition closed',
        'body' => 'Offers are closed for “:competition_title”.',
        'mail_subject' => 'Competition closed: :competition_title',
        'mail_intro' => 'Offers are closed for “:competition_title”. You can now review and evaluate the offers, then award or close without award.',
        'mail_action' => 'Review offers',
    ],

    'competition_cancelled' => [
        'title' => 'Competition cancelled',
        'body' => '“:competition_title” was cancelled. Reason: :reason',
        'mail_subject' => 'Competition cancelled: :competition_title',
        'mail_intro' => '“:competition_title” was cancelled. Reason: :reason',
        'mail_action' => 'View competition',
    ],

    'competition_not_awarded' => [
        'title' => 'Closed without award',
        'body' => 'The issuer closed “:competition_title” without award.',
        'mail_subject' => 'Closed without award: :competition_title',
        'mail_intro' => 'The issuer closed “:competition_title” without award. Thank you for taking part.',
        'mail_action' => 'View competition',
    ],

    'offer_received' => [
        'title' => 'New offers',
        'body' => 'New offers arrived in “:competition_title”.',
    ],

    'standing_lost_lead' => [
        'title' => 'You are no longer leading',
        'body' => 'Your offer is no longer the leading offer in “:competition_title”.',
    ],

    'bafo_invited' => [
        'title' => 'Best-and-final-offer round',
        'body' => 'You are invited to submit your best and final offer for “:competition_title” before :cutoff_time.',
        'mail_subject' => 'Best-and-final-offer round: :competition_title',
        'mail_intro' => 'You are invited to submit your best and final offer for “:competition_title” before :cutoff_time. You can submit one offer in this round.',
        'mail_action' => 'Submit your final offer',
    ],

    'bafo_ended' => [
        'title' => 'BAFO round ended',
        'body' => 'The best-and-final-offer round of “:competition_title” has ended. You can now award.',
    ],

    'award_won' => [
        'title' => 'Competition awarded to you',
        'body' => '“:competition_title” has been awarded to you.',
        'mail_subject' => 'Competition awarded to you: :competition_title',
        'mail_intro' => '“:competition_title” has been awarded to you. You can find the details on the competition page.',
        'mail_action' => 'View competition',
        'mail_message_label' => 'Message from the issuer:',
    ],

    'award_not_selected' => [
        'title' => 'Competition result',
        'body' => '“:competition_title” was awarded to another participant. Thank you for taking part.',
        'mail_subject' => 'Competition result: :competition_title',
        'mail_intro' => '“:competition_title” was awarded to another participant. Thank you for taking part.',
        'mail_action' => 'View competition',
    ],

    'award_revoked' => [
        'title' => 'Award revoked',
        'body' => 'The issuer revoked the award of “:competition_title”. Reason: :reason',
        'mail_subject' => 'Award revoked: :competition_title',
        'mail_intro' => 'The issuer revoked the award of “:competition_title”. Reason: :reason',
        'mail_action' => 'View competition',
    ],

    'offer_voided' => [
        'title' => 'An offer was voided',
        'body' => 'The platform voided an offer in “:competition_title”.',
        'mail_subject' => 'An offer was voided in :competition_title',
        'mail_intro' => 'The platform voided one of your offers in “:competition_title”. You can review your offers on the competition page.',
        'mail_action' => 'View competition',
    ],

    'comment_created' => [
        'title' => 'New Q&A message',
        'body' => 'There is a new message in the Q&A of “:competition_title”.',
    ],

    'invitation_joined' => [
        'title' => 'Participant joined',
        'body' => ':organization_name joined “:competition_title”.',
        'body_sponsored_suffix' => ' Participation fees are covered.',
    ],

    'invitation_declined' => [
        'title' => 'Invitation declined',
        'body' => ':organization_name declined to take part in “:competition_title”.',
    ],

    'subscription_activated' => [
        'title' => 'Subscription active',
        'body' => 'Your :plan_name plan is active until :ends_at.',
        'mail_subject' => 'Your BAFO subscription is active',
        'mail_intro' => 'Your :plan_name plan is active until :ends_at.',
        'mail_action' => 'Manage subscription',
    ],

    'subscription_expiring' => [
        'title' => 'Subscription ending soon',
        'body' => '{1} Your subscription ends tomorrow.|[2,*] Your subscription ends in :days_left days.',
        'mail_subject' => 'Your subscription ends soon',
        'mail_intro' => 'Your :plan_name plan ends on :ends_at. Renew it to keep issuing competitions.',
        'mail_action' => 'Renew subscription',
    ],

    'subscription_expired' => [
        'title' => 'Subscription expired',
        'body' => 'Your :plan_name plan has expired.',
        'mail_subject' => 'Your BAFO subscription has expired',
        'mail_intro' => 'Your :plan_name plan has expired. Renew it to keep issuing competitions.',
        'mail_action' => 'Renew subscription',
    ],

    'payment_failed' => [
        'title' => 'Payment failed',
        'body' => 'The payment was not completed. You can try again.',
        'mail_subject' => 'Payment failed',
        'mail_intro' => 'The payment was not completed. You can try again.',
        'mail_action' => 'Go to billing',
    ],

    'invoice_issued' => [
        'title' => 'New tax invoice',
        'body' => 'Invoice :number has been issued.',
        'mail_subject' => 'New tax invoice :number',
        'mail_intro' => 'Invoice :number has been issued.',
        'mail_action' => 'View invoice',
        'mail_download_hint' => 'You can download the PDF from the billing page of your dashboard.',
    ],

    'sponsorship_unused_passes' => [
        'title' => 'Unused sponsored participation passes',
        'body' => 'Unused sponsored participation passes in “:competition_title”: :unused_count. The platform team will issue a voucher for their value.',
        'mail_subject' => 'Unused sponsored participation passes: :competition_title',
        'mail_intro' => 'Unused sponsored participation passes in “:competition_title”: :unused_count. The platform team will issue a voucher for their value.',
        'mail_action' => 'View competition',
    ],

    'sponsorship_publish_failed' => [
        'title' => 'Paid, not yet published',
        'body' => 'We received your payment but could not publish “:competition_title”. Review it and publish again. You will not be charged again.',
        'mail_subject' => 'Paid, not yet published: :competition_title',
        'mail_intro' => 'We received your payment but could not publish “:competition_title”. Review it and publish again. You will not be charged again.',
        'mail_action' => 'Review competition',
        'mail_reason' => 'Reason: :reason',
    ],

    'voucher_issued' => [
        'title' => 'New voucher',
        'body' => 'Voucher :code worth :amount was added to your account.',
        'mail_subject' => 'A new voucher in your account',
        'mail_intro' => 'Voucher :code worth :amount was added to your account. You can use it at checkout.',
        'mail_action' => 'Go to billing',
    ],

    'webhook_endpoint_disabled' => [
        'title' => 'Webhook endpoint disabled',
        'body' => 'The endpoint :url was disabled after repeated delivery failures.',
        'mail_subject' => 'Webhook endpoint disabled',
        'mail_intro' => 'The endpoint :url was disabled after repeated delivery failures. Check it, then re-enable it on the integrations page.',
        'mail_action' => 'Manage integrations',
    ],

    'import_finished' => [
        'title' => 'Import finished',
        'body' => 'Vendor import finished: :created created, :updated updated, :errors errors.',
    ],

    'export_finished' => [
        'title' => 'Export ready',
        'body' => 'Your export file is ready to download.',
    ],

    // ---------------------------------------------------------------- shared texts

    'reason_unspecified' => 'Not specified',

    'mail' => [
        'brand' => 'BAFO',
        'greeting' => 'Hello :name,',
        'salutation' => 'Regards,',
        'signature' => 'The BAFO team',
        'footer_reason' => 'You are receiving this e-mail because you have a BAFO account.',
        'rights' => 'All rights reserved.',
    ],

    // Display time (CONVENTIONS §9.1): "9 Nov 2026, 3:30 PM".
    'time' => [
        'months' => [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Aug',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dec',
        ],
        'am' => 'AM',
        'pm' => 'PM',
        'separator' => ', ',
    ],

    'attributes' => [
        'unread' => 'unread only',
        'page' => 'page',
        'per_page' => 'items per page',
        'token' => 'device token',
        'platform' => 'platform',
        'device_name' => 'device name',
        'app_version' => 'app version',
    ],

    'validation' => [
        'semver' => 'The :attribute must be a version number such as 1.0.0.',
    ],

    'preview' => [
        'recipient_name' => 'Sara Alotaibi',
    ],

    'enums' => [
        'device_platform' => [
            'ios' => 'iOS',
            'android' => 'Android',
            'web' => 'Web',
        ],
        // Client labels (filters, settings), keyed by the catalogue type (§11.3).
        'notification_type' => [
            'competition' => [
                'invited' => 'Competition invitation',
                'updated' => 'Competition update',
                'opened' => 'Offers open',
                'final_window_started' => 'Final pricing window',
                'closing_soon' => 'Closing soon',
                'extended' => 'Closing time extended',
                'closed' => 'Competition closed',
                'cancelled' => 'Competition cancelled',
                'not_awarded' => 'Closed without award',
            ],
            'offer' => [
                'received' => 'New offers',
                'voided' => 'Offer voided',
            ],
            'standing' => [
                'lost_lead' => 'Leading offer changed',
            ],
            'bafo' => [
                'invited' => 'Best-and-final-offer round invitation',
                'ended' => 'Best-and-final-offer round ended',
            ],
            'award' => [
                'won' => 'Awarded to you',
                'not_selected' => 'Competition result',
                'revoked' => 'Award revoked',
            ],
            'comment' => [
                'created' => 'Q&A message',
            ],
            'invitation' => [
                'joined' => 'Participant joined',
                'declined' => 'Invitation declined',
            ],
            'subscription' => [
                'activated' => 'Subscription activated',
                'expiring' => 'Subscription ending soon',
                'expired' => 'Subscription expired',
            ],
            'payment' => [
                'failed' => 'Payment failed',
            ],
            'invoice' => [
                'issued' => 'Tax invoice',
            ],
            'sponsorship' => [
                'unused_passes' => 'Unused sponsored participation passes',
                'publish_failed' => 'Publish failed after payment',
            ],
            'voucher' => [
                'issued' => 'New voucher',
            ],
            'webhook' => [
                'endpoint_disabled' => 'Webhook endpoint disabled',
            ],
            'import' => [
                'finished' => 'Import finished',
            ],
            'export' => [
                'finished' => 'Export ready',
            ],
        ],
        'delivery_channel' => [
            'database' => 'In-app',
            'push' => 'Push',
            'mail' => 'E-mail',
        ],
    ],

];
