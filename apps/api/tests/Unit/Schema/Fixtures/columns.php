<?php

declare(strict_types=1);

/*
 * The exact column list of every module table (ARCHITECTURE §5.2–§5.9), in migration order.
 * tests/Unit/Schema/MigrationsTest.php compares it with the migrated database.
 *
 * @return array<string, list<string>>
 */

return [
    'regions' => [
        'id', 'public_id', 'code', 'name', 'sort_order', 'is_active', 'created_at', 'updated_at',
    ],
    'categories' => [
        'id', 'public_id', 'code', 'name', 'is_other', 'auction_allowed', 'sort_order', 'is_active',
        'created_at', 'updated_at',
    ],
    'close_reasons' => [
        'id', 'public_id', 'code', 'kind', 'name', 'requires_note', 'sort_order', 'is_active', 'created_at',
        'updated_at',
    ],
    'competition_presets' => [
        'id', 'public_id', 'code', 'name', 'description', 'direction', 'format', 'rules', 'sort_order',
        'is_active', 'created_at', 'updated_at',
    ],
    'organizations' => [
        'id', 'public_id', 'name', 'legal_name_ar', 'legal_name_en', 'cr_number', 'vat_registered',
        'vat_number', 'region_id', 'city', 'address_building_number', 'address_street', 'address_district',
        'address_postal_code', 'address_additional_number', 'address_short', 'website', 'email', 'phone',
        'logo_file_id', 'profile_file_id', 'visible_in_suggestions', 'status', 'verified_at',
        'suspended_at', 'suspension_reason', 'api_enabled', 'auction_enabled', 'sponsorship_enabled',
        'trial_used_at', 'created_at', 'updated_at', 'deleted_at',
    ],
    'organization_category' => [
        'organization_id', 'category_id',
    ],
    'users' => [
        'id', 'public_id', 'name', 'email', 'phone', 'password', 'email_verified_at', 'locale',
        'avatar_file_id', 'status', 'last_login_at', 'created_at', 'updated_at', 'deleted_at',
    ],
    'memberships' => [
        'id', 'public_id', 'organization_id', 'user_id', 'role', 'can_award', 'can_purchase', 'status',
        'invited_by_user_id', 'invite_token_hash', 'invite_expires_at', 'joined_at', 'created_at',
        'updated_at',
    ],
    'otp_codes' => [
        'id', 'email', 'user_id', 'purpose', 'code_hash', 'context', 'attempts', 'expires_at',
        'consumed_at', 'ip', 'created_at',
    ],
    'consents' => [
        'id', 'user_id', 'organization_id', 'document_code', 'document_version', 'locale', 'accepted_at',
        'ip', 'user_agent',
    ],
    'account_deletion_requests' => [
        'id', 'public_id', 'user_id', 'organization_id', 'scope', 'reason', 'status', 'scheduled_for',
        'cancelled_at', 'completed_at', 'created_at', 'updated_at',
    ],
    'vendors' => [
        'id', 'public_id', 'organization_id', 'name', 'name_en', 'cr_number', 'vat_number', 'email',
        'contact_name', 'phone', 'region_id', 'city', 'status', 'linked_organization_id', 'source', 'notes',
        'created_at', 'updated_at',
    ],
    'vendor_category' => [
        'vendor_id', 'category_id',
    ],
    'external_refs' => [
        'id', 'organization_id', 'refable_type', 'refable_id', 'system', 'type', 'value', 'number', 'url',
        'created_by_api_client_id', 'created_at', 'updated_at',
    ],
    'api_clients' => [
        'id', 'public_id', 'organization_id', 'name', 'description', 'scopes', 'status', 'oauth_client_id',
        'created_by_user_id', 'last_used_at', 'last_used_ip', 'revoked_at', 'created_at', 'updated_at',
    ],
    'api_keys' => [
        'id', 'public_id', 'api_client_id', 'prefix', 'key_hash', 'last_four', 'expires_at', 'revoked_at',
        'last_used_at', 'last_used_ip', 'created_by_user_id', 'created_at', 'updated_at',
    ],
    'webhook_endpoints' => [
        'id', 'public_id', 'organization_id', 'url', 'description', 'event_types', 'secret', 'status',
        'disabled_reason', 'failing_since', 'last_success_at', 'last_failure_at', 'created_by_user_id',
        'created_by_api_client_id', 'created_at', 'updated_at', 'deleted_at',
    ],
    'webhook_events' => [
        'id', 'public_id', 'organization_id', 'type', 'subject_type', 'subject_id', 'sequence', 'payload',
        'occurred_at', 'dispatched_at', 'created_at',
    ],
    'webhook_deliveries' => [
        'id', 'public_id', 'webhook_event_id', 'webhook_endpoint_id', 'status', 'attempts',
        'next_attempt_at', 'last_attempt_at', 'last_http_status', 'last_error', 'last_response_excerpt',
        'last_duration_ms', 'succeeded_at', 'failed_at', 'created_at', 'updated_at',
    ],
    'import_jobs' => [
        'id', 'public_id', 'organization_id', 'created_by_user_id', 'type', 'mode', 'status',
        'source_file_id', 'errors_file_id', 'total_rows', 'valid_rows', 'created_rows', 'updated_rows',
        'error_rows', 'errors_preview', 'failure_message', 'finished_at', 'created_at', 'updated_at',
    ],
    'export_jobs' => [
        'id', 'public_id', 'organization_id', 'created_by_user_id', 'type', 'format', 'filters', 'status',
        'file_id', 'row_count', 'failure_message', 'finished_at', 'created_at', 'updated_at',
    ],
    'competitions' => [
        'id', 'public_id', 'reference_no', 'organization_id', 'created_by_user_id',
        'created_by_api_client_id', 'source', 'title', 'description', 'category_id', 'category_other_text',
        'region_id', 'direction', 'format', 'status', 'currency', 'preset_code', 'start_price_minor',
        'reserve_price_minor', 'min_step_minor', 'min_step_bps', 'amount_granularity_minor', 'must_beat',
        'rank_visibility', 'show_prices', 'auto_extend_enabled', 'auto_extend_window_seconds',
        'auto_extend_by_seconds', 'auto_extend_max', 'final_window_minutes', 'bafo_round_enabled',
        'bafo_duration_minutes', 'min_participants', 'result_publication', 'bidding_opens_at',
        'scheduled_close_at', 'effective_close_at', 'hard_stop_at', 'final_window_starts_at',
        'invitation_cutoff_at', 'extension_count', 'notified_thresholds', 'published_at', 'opened_at',
        'final_window_started_at', 'closed_at', 'offers_opened_at', 'awarded_at', 'not_awarded_at',
        'cancelled_at', 'cancel_reason_id', 'cancel_note', 'cancelled_by_user_id', 'cancelled_by_admin_id',
        'not_awarded_reason_id', 'not_awarded_note', 'created_at', 'updated_at', 'deleted_at',
    ],
    'competition_extensions' => [
        'id', 'public_id', 'competition_id', 'kind', 'previous_close_at', 'new_close_at',
        'triggered_by_offer_id', 'actor_user_id', 'actor_admin_id', 'reason', 'created_at',
    ],
    'competition_attachments' => [
        'id', 'public_id', 'competition_id', 'kind', 'file_id', 'title', 'url', 'is_addendum',
        'uploaded_by_user_id', 'sort_order', 'created_at', 'updated_at',
    ],
    'invitations' => [
        'id', 'public_id', 'competition_id', 'email', 'name', 'organization_id', 'vendor_id', 'status',
        'sponsored_requested', 'token_hash', 'invited_by_user_id', 'invited_by_api_client_id', 'sent_at',
        'viewed_at', 'joined_at', 'declined_at', 'revoked_at', 'expired_at', 'decline_reason',
        'revoke_reason', 'created_at', 'updated_at',
    ],
    'participants' => [
        'id', 'public_id', 'competition_id', 'organization_id', 'invitation_id', 'alias_no',
        'entitlement_source', 'terms_version', 'terms_accepted_at', 'terms_ip', 'joined_by_user_id',
        'created_at', 'updated_at',
    ],
    'comments' => [
        'id', 'public_id', 'competition_id', 'parent_id', 'author_user_id', 'author_organization_id',
        'author_participant_id', 'is_issuer', 'body', 'created_at', 'updated_at',
    ],
    'competition_live_states' => [
        'competition_id', 'version', 'last_seq', 'leader_participant_id', 'leader_offer_id',
        'leader_amount_minor', 'accepted_offer_count', 'participants_with_offers', 'reserve_met',
        'ledger_head_hash', 'updated_at',
    ],
    'offers' => [
        'id', 'public_id', 'competition_id', 'participant_id', 'organization_id', 'submitted_by_user_id',
        'seq', 'stage', 'amount_minor', 'rank_key', 'accepted_at', 'idempotency_key', 'channel', 'ip',
        'user_agent', 'outlier_confirmed', 'prev_hash', 'hash', 'created_at',
    ],
    'offer_voids' => [
        'id', 'public_id', 'offer_id', 'reason_id', 'note', 'voided_by_admin_id', 'created_at',
    ],
    'offer_rejections' => [
        'id', 'competition_id', 'participant_id', 'user_id', 'amount_minor', 'code', 'idempotency_key',
        'stage', 'received_at', 'db_time', 'channel', 'ip', 'created_at',
    ],
    'participant_standings' => [
        'participant_id', 'competition_id', 'current_offer_id', 'current_amount_minor', 'current_rank_key',
        'current_at', 'current_seq', 'first_amount_minor', 'offers_count', 'rank', 'is_leader',
        'bafo_shortlisted', 'bafo_reference_amount_minor', 'bafo_offer_id', 'last_offer_at', 'updated_at',
    ],
    'bafo_rounds' => [
        'id', 'public_id', 'competition_id', 'started_by_user_id', 'status', 'starts_at', 'cutoff_at',
        'ended_at', 'shortlist_count', 'created_at', 'updated_at',
    ],
    'awards' => [
        'id', 'public_id', 'competition_id', 'participant_id', 'organization_id', 'offer_id',
        'amount_minor', 'currency', 'status', 'is_leading_offer', 'rank_at_award', 'reserve_met',
        'justification_reason_id', 'justification_text', 'message_to_winner', 'internal_notes',
        'awarded_by_user_id', 'awarded_at', 'revoked_by_user_id', 'revoked_at', 'revoke_reason',
        'erp_sync_status', 'erp_sync_message', 'erp_synced_at', 'ledger_head_hash', 'created_at',
        'updated_at',
    ],
    'competition_reports' => [
        'id', 'competition_id', 'locale', 'status', 'file_id', 'live_version', 'generated_at', 'created_at',
        'updated_at',
    ],
    'plans' => [
        'id', 'public_id', 'code', 'name', 'description', 'features', 'seats', 'monthly_price_minor',
        'annual_price_minor', 'monthly_list_price_minor', 'annual_list_price_minor', 'is_custom',
        'is_featured', 'is_active', 'sort_order', 'created_at', 'updated_at',
    ],
    'coupons' => [
        'id', 'public_id', 'code', 'kind', 'discount_type', 'percent_bps', 'amount_minor', 'balance_minor',
        'applies_to', 'organization_id', 'max_redemptions', 'redemptions_count', 'per_organization_limit',
        'valid_from', 'valid_until', 'is_active', 'reason', 'source_competition_id', 'created_by_admin_id',
        'created_at', 'updated_at',
    ],
    'payments' => [
        'id', 'public_id', 'organization_id', 'created_by_user_id', 'purpose', 'status', 'gateway',
        'gateway_reference', 'currency', 'subtotal_minor', 'discount_minor', 'credit_minor', 'vat_rate_bp',
        'vat_minor', 'total_minor', 'coupon_id', 'idempotency_key', 'return_url', 'redirect_url',
        'metadata', 'expires_at', 'paid_at', 'failed_at', 'refunded_at', 'failure_code', 'failure_message',
        'refund_reference', 'refunded_by_admin_id', 'manual_reference', 'created_at', 'updated_at',
    ],
    'payment_lines' => [
        'id', 'payment_id', 'kind', 'description', 'quantity', 'unit_price_minor', 'net_minor', 'ref_type',
        'ref_id', 'created_at', 'updated_at',
    ],
    'coupon_redemptions' => [
        'id', 'coupon_id', 'organization_id', 'payment_id', 'amount_minor', 'redeemed_at', 'created_at',
        'updated_at',
    ],
    'subscriptions' => [
        'id', 'public_id', 'organization_id', 'plan_id', 'source', 'interval', 'seats', 'status',
        'starts_at', 'ends_at', 'unit_price_minor', 'subtotal_minor', 'discount_minor', 'credit_minor',
        'vat_minor', 'total_minor', 'payment_id', 'replaces_subscription_id', 'granted_by_admin_id',
        'grant_reason', 'activated_at', 'superseded_at', 'expired_at', 'cancelled_at', 'reminders_sent',
        'created_at', 'updated_at',
    ],
    'competition_sponsorships' => [
        'id', 'public_id', 'competition_id', 'organization_id', 'mode', 'max_passes', 'unit_price_minor',
        'vat_rate_bp', 'funded_passes', 'status', 'settled_at', 'unused_count', 'voucher_coupon_id',
        'configured_by_user_id', 'created_at', 'updated_at',
    ],
    'sponsored_passes' => [
        'id', 'public_id', 'sponsorship_id', 'competition_id', 'invitation_id', 'organization_id',
        'payment_id', 'source', 'status', 'release_reason', 'hold_expires_at', 'reserved_at', 'joined_at',
        'released_at', 'settled_at', 'voided_at', 'created_at', 'updated_at',
    ],
    'invoices' => [
        'id', 'public_id', 'number', 'organization_id', 'payment_id', 'type', 'original_invoice_id',
        'issue_date', 'supply_date', 'currency', 'subtotal_minor', 'discount_minor', 'vat_minor',
        'total_minor', 'vat_rate_bp', 'seller_snapshot', 'buyer_snapshot', 'einvoice_provider',
        'einvoice_status', 'einvoice_document_id', 'zatca_uuid', 'qr_payload', 'einvoice_attempts',
        'einvoice_last_error', 'pdf_file_id', 'issued_at', 'cleared_at', 'created_at', 'updated_at',
    ],
    'invoice_lines' => [
        'id', 'invoice_id', 'description', 'quantity', 'unit_price_minor', 'net_minor', 'created_at',
        'updated_at',
    ],
    'notifications' => [
        'id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at', 'updated_at',
    ],
    'device_tokens' => [
        'id', 'public_id', 'user_id', 'token', 'platform', 'device_name', 'app_version', 'locale',
        'last_seen_at', 'created_at', 'updated_at',
    ],
    'admins' => [
        'id', 'public_id', 'name', 'email', 'password', 'role', 'is_active', 'app_authentication_secret',
        'app_authentication_recovery_codes', 'last_login_at', 'remember_token', 'created_at', 'updated_at',
    ],
];
