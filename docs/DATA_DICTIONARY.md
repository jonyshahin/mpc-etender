# MPC e-Tender Data Dictionary — Quick Reference for Migrations

> Place this file at `docs/DATA_DICTIONARY.md` in the project root.
> This is the authoritative column specification for all 32 tables.
> Every migration MUST match these specs exactly.

## Conventions

- All PKs: `$table->uuid('id')->primary()`
- All FKs: `$table->foreignUuid('xxx_id')->constrained('table_name')` with appropriate cascade
- Timestamps: `$table->timestamps()` on all tables EXCEPT audit_logs, document_access_logs, activity_logs (only created_at)
- Enums stored as `$table->string('field', 30)` — validated at app layer via PHP enums
- JSON columns: `$table->json('field')`
- Encrypted text: `$table->text('field')->nullable()` — encryption at app layer

---

## 1. roles

```
uuid id PK
string name 100 NOT NULL
string slug 100 NOT NULL UNIQUE
text description NULLABLE
boolean is_system DEFAULT false
timestamps
```

## 2. permissions

```
uuid id PK
string name 255 NOT NULL
string slug 255 NOT NULL UNIQUE
string module 100 NOT NULL          -- vendors, tenders, bids, evaluations, admin, reports
text description NULLABLE
timestamps
```

## 3. role_permissions

```
uuid id PK
foreignUuid role_id -> roles CASCADE
foreignUuid permission_id -> permissions CASCADE
timestamp created_at
UNIQUE(role_id, permission_id)
```

## 4. users (modify starter kit migration)

```
uuid id PK
string name 255 NOT NULL
string email 255 NOT NULL UNIQUE
timestamp email_verified_at NULLABLE
string password 255 NOT NULL
string phone 50 NULLABLE
foreignUuid role_id -> roles RESTRICT
string language_pref 2 DEFAULT 'en'    -- en, ar
boolean is_2fa_enabled DEFAULT false
text two_factor_secret NULLABLE
string avatar_path 500 NULLABLE
boolean is_active DEFAULT true
timestamp last_login_at NULLABLE
rememberToken
timestamps
```

## 5. projects

```
uuid id PK
string name 255 NOT NULL
string name_ar 255 NULLABLE
string code 50 NOT NULL UNIQUE
text description NULLABLE
string location 255 NULLABLE
string client_name 255 NULLABLE
string status 30 DEFAULT 'active'       -- active, on_hold, completed, cancelled
date start_date NULLABLE
date end_date NULLABLE
foreignUuid created_by -> users NULLABLE SET NULL
timestamps
```

## 6. user_project

```
uuid id PK
foreignUuid user_id -> users CASCADE
foreignUuid project_id -> projects CASCADE
string project_role 30 NOT NULL         -- project_manager, procurement_officer, evaluator, viewer
timestamp assigned_at DEFAULT now
foreignUuid assigned_by -> users NULLABLE SET NULL
timestamps
UNIQUE(user_id, project_id)
```

## 7. categories

```
uuid id PK
string name_en 255 NOT NULL
string name_ar 255 NOT NULL
foreignUuid parent_id -> categories NULLABLE SET NULL   -- self-referencing tree
text description NULLABLE
boolean is_active DEFAULT true
integer sort_order DEFAULT 0
timestamps
```

## 8. vendors

```
uuid id PK
string company_name 255 NOT NULL
string company_name_ar 255 NULLABLE
string trade_license_no 100 NULLABLE
string contact_person 255 NOT NULL
string email 255 NOT NULL UNIQUE
string password 255 NOT NULL
string phone 50 NULLABLE
string whatsapp_number 50 NULLABLE
text address NULLABLE
string city 100 NULLABLE
string country 100 NULLABLE
string website 500 NULLABLE
string prequalification_status 30 DEFAULT 'pending'  -- pending, under_review, qualified, rejected, suspended, blacklisted
timestamp qualified_at NULLABLE
foreignUuid qualified_by -> users NULLABLE SET NULL
text rejection_reason NULLABLE
string language_pref 2 DEFAULT 'ar'
boolean is_active DEFAULT true
timestamp last_login_at NULLABLE
rememberToken
timestamps

INDEX(prequalification_status)
INDEX(whatsapp_number)
```

## 9. vendor_documents

```
uuid id PK
foreignUuid vendor_id -> vendors CASCADE
string document_type 30 NOT NULL        -- trade_license, insurance, financial_statement, reference, certificate, other
string title 255 NOT NULL
string file_path 500 NOT NULL
integer file_size NULLABLE
string mime_type 100 NULLABLE
date issue_date NULLABLE
date expiry_date NULLABLE
string status 30 DEFAULT 'pending'      -- pending, approved, rejected, expired
foreignUuid reviewed_by -> users NULLABLE SET NULL
timestamp reviewed_at NULLABLE
text review_notes NULLABLE
timestamps
```

## 10. vendor_categories

```
uuid id PK
foreignUuid vendor_id -> vendors CASCADE
foreignUuid category_id -> categories CASCADE
timestamp created_at
UNIQUE(vendor_id, category_id)
```

## 11. tenders

```
uuid id PK
foreignUuid project_id -> projects CASCADE
foreignUuid created_by -> users RESTRICT
string reference_number 50 NOT NULL UNIQUE
string title_en 500 NOT NULL
string title_ar 500 NULLABLE
text description_en NULLABLE
text description_ar NULLABLE
string tender_type 30 NOT NULL          -- open, restricted, direct_invitation, framework
string status 30 DEFAULT 'draft'        -- draft, published, submission_closed, under_evaluation, awarded, completed, cancelled
decimal estimated_value 15,2 NULLABLE
string currency 3 DEFAULT 'USD'
timestamp publish_date NULLABLE
timestamp submission_deadline NOT NULL
timestamp opening_date NOT NULL
boolean is_two_envelope DEFAULT false
decimal technical_pass_score 5,2 NULLABLE
boolean requires_site_visit DEFAULT false
timestamp site_visit_date NULLABLE
text notes_internal NULLABLE
text cancelled_reason NULLABLE
timestamps

INDEX(project_id, status)
INDEX(submission_deadline)
```

## 12. tender_categories

```
uuid id PK
foreignUuid tender_id -> tenders CASCADE
foreignUuid category_id -> categories CASCADE
timestamp created_at
UNIQUE(tender_id, category_id)
```

## 13. tender_documents

```
uuid id PK
foreignUuid tender_id -> tenders CASCADE
foreignUuid uploaded_by -> users RESTRICT
string title 255 NOT NULL
string file_path 500 NOT NULL
integer file_size NULLABLE
string mime_type 100 NULLABLE
string doc_type 30 NOT NULL             -- specification, drawing, contract_terms, boq_template, site_photo, other
integer version DEFAULT 1
boolean is_current DEFAULT true
timestamps
```

## 14. boq_sections

```
uuid id PK
foreignUuid tender_id -> tenders CASCADE
string title 255 NOT NULL
string title_ar 255 NULLABLE
integer sort_order DEFAULT 0
timestamps
```

## 15. boq_items

```
uuid id PK
foreignUuid section_id -> boq_sections CASCADE
string item_code 50 NOT NULL
text description_en NOT NULL
text description_ar NULLABLE
string unit 50 NOT NULL                 -- m³, kg, lm, m², ls, nr, etc.
decimal quantity 15,3 NOT NULL
integer sort_order DEFAULT 0
text notes NULLABLE
timestamps
```

## 16. addenda

```
uuid id PK
foreignUuid tender_id -> tenders CASCADE
foreignUuid issued_by -> users RESTRICT
integer addendum_number NOT NULL
string subject 255 NOT NULL
text content_en NOT NULL
text content_ar NULLABLE
string file_path 500 NULLABLE
boolean extends_deadline DEFAULT false
timestamp new_deadline NULLABLE
timestamp published_at
timestamps
```

## 17. clarifications

```
uuid id PK
foreignUuid tender_id -> tenders CASCADE
foreignUuid asked_by -> vendors NULLABLE SET NULL
foreignUuid answered_by -> users NULLABLE SET NULL
text question NOT NULL
text answer NULLABLE
boolean is_published DEFAULT false
timestamp asked_at DEFAULT now
timestamp answered_at NULLABLE
timestamp published_at NULLABLE
timestamps
```

## 18. bids

```
uuid id PK
foreignUuid tender_id -> tenders CASCADE
foreignUuid vendor_id -> vendors CASCADE
string bid_reference 50 NOT NULL UNIQUE
string envelope_type 30 DEFAULT 'single'  -- single, technical, financial
text encrypted_pricing_data NULLABLE
decimal total_amount 15,2 NULLABLE
string currency 3 DEFAULT 'USD'
text technical_notes NULLABLE
string status 30 DEFAULT 'draft'        -- draft, submitted, withdrawn, opened, under_evaluation, accepted, rejected, disqualified
boolean is_sealed DEFAULT true
timestamp submitted_at NULLABLE
timestamp opened_at NULLABLE
foreignUuid opened_by -> users NULLABLE SET NULL
text withdrawal_reason NULLABLE
string submission_ip 45 NULLABLE
string submission_user_agent 500 NULLABLE
timestamps

UNIQUE(tender_id, vendor_id)
INDEX(tender_id, status)
```

## 19. bid_boq_prices

```
uuid id PK
foreignUuid bid_id -> bids CASCADE
foreignUuid boq_item_id -> boq_items CASCADE
decimal unit_price 15,4 NOT NULL
decimal total_price 15,2 NOT NULL
text remarks NULLABLE
timestamps
```

## 20. bid_documents

```
uuid id PK
foreignUuid bid_id -> bids CASCADE
string title 255 NOT NULL
string file_path 500 NOT NULL
integer file_size NULLABLE
string mime_type 100 NULLABLE
string doc_type 30 NOT NULL             -- technical_proposal, method_statement, certificate, financial_schedule, other
timestamp uploaded_at DEFAULT now
timestamps
```

## 21. evaluation_criteria

```
uuid id PK
foreignUuid tender_id -> tenders CASCADE
string name_en 255 NOT NULL
string name_ar 255 NULLABLE
string envelope 30 DEFAULT 'technical'  -- technical, financial
decimal weight_percentage 5,2 NOT NULL
decimal max_score 5,2 DEFAULT 100
text description NULLABLE
integer sort_order DEFAULT 0
timestamps
```

## 22. evaluation_committees

```
uuid id PK
foreignUuid tender_id -> tenders CASCADE
string name 255 NOT NULL
string committee_type 30 DEFAULT 'technical'  -- technical, financial, combined
string status 30 DEFAULT 'pending'      -- pending, in_progress, completed
timestamp formed_at DEFAULT now
timestamp completed_at NULLABLE
timestamps
```

## 23. committee_members

```
uuid id PK
foreignUuid committee_id -> evaluation_committees CASCADE
foreignUuid user_id -> users CASCADE
string role 30 DEFAULT 'member'         -- chair, member, secretary
boolean has_scored DEFAULT false
timestamp scored_at NULLABLE
timestamps
UNIQUE(committee_id, user_id)
```

## 24. evaluation_scores

```
uuid id PK
foreignUuid bid_id -> bids CASCADE
foreignUuid criterion_id -> evaluation_criteria CASCADE
foreignUuid evaluator_id -> users CASCADE
decimal score 5,2 NOT NULL
text justification NULLABLE
timestamp scored_at DEFAULT now
timestamps

UNIQUE(bid_id, criterion_id, evaluator_id)
```

## 25. evaluation_reports

```
uuid id PK
foreignUuid tender_id -> tenders CASCADE
foreignUuid generated_by -> users RESTRICT
string report_type 30 DEFAULT 'final'   -- technical_only, financial_only, final
text summary NULLABLE
json ranking_data NOT NULL
foreignUuid recommended_bid_id -> bids NULLABLE SET NULL
string status 30 DEFAULT 'draft'        -- draft, finalized, approved
string file_path 500 NULLABLE
timestamp generated_at DEFAULT now
timestamps
```

## 26. approval_requests

```
uuid id PK
foreignUuid tender_id -> tenders CASCADE
foreignUuid report_id -> evaluation_reports CASCADE
foreignUuid requested_by -> users RESTRICT
string approval_type 30 NOT NULL        -- award, cancellation, extension, budget_override
decimal value_threshold 15,2 NULLABLE
integer approval_level DEFAULT 1
string status 30 DEFAULT 'pending'      -- pending, approved, rejected, escalated, expired
timestamp requested_at DEFAULT now
timestamp deadline NULLABLE
timestamps
```

## 27. approval_decisions

```
uuid id PK
foreignUuid request_id -> approval_requests CASCADE
foreignUuid approver_id -> users RESTRICT
string decision 30 NOT NULL             -- approved, rejected, returned_for_revision
text comments NULLABLE
foreignUuid delegated_from -> users NULLABLE SET NULL
timestamp decided_at DEFAULT now
timestamps
```

## 28. awards

```
uuid id PK
foreignUuid tender_id -> tenders CASCADE
foreignUuid bid_id -> bids CASCADE
foreignUuid vendor_id -> vendors CASCADE
foreignUuid approved_by -> users RESTRICT
decimal award_amount 15,2 NOT NULL
string currency 3 DEFAULT 'USD'
text justification NULLABLE
string status 30 DEFAULT 'pending'      -- pending, notified, accepted, declined
string letter_file_path 500 NULLABLE
timestamp awarded_at DEFAULT now
timestamp notified_at NULLABLE
timestamp accepted_at NULLABLE
timestamps
```

## 29. notifications

```
uuid id PK
foreignUuid user_id -> users NULLABLE SET NULL
foreignUuid vendor_id -> vendors NULLABLE SET NULL
string notifiable_type 255 NOT NULL
uuid notifiable_id NOT NULL
string notification_type 30 NOT NULL
string title_en 500 NOT NULL
string title_ar 500 NULLABLE
text body_en NOT NULL
text body_ar NULLABLE
json data NULLABLE
timestamp read_at NULLABLE
timestamp created_at

INDEX(user_id, read_at)
INDEX(vendor_id, read_at)
```

## 30. notification_logs

```
uuid id PK
foreignUuid notification_id -> notifications CASCADE
string channel 30 NOT NULL              -- whatsapp, sms, email, in_app, broadcast
string delivery_status 30 DEFAULT 'queued'  -- queued, sent, delivered, failed, bounced
string external_message_id 255 NULLABLE
text error_message NULLABLE
integer retry_count DEFAULT 0
timestamp sent_at NULLABLE
timestamp delivered_at NULLABLE
timestamps

INDEX(notification_id, channel)
```

## 31. notification_templates

```
uuid id PK
string slug 100 NOT NULL UNIQUE
string channel 30 NOT NULL
string notification_type 30 NOT NULL
string subject_en 500 NULLABLE
string subject_ar 500 NULLABLE
text body_template_en NOT NULL
text body_template_ar NOT NULL
string whatsapp_template_name 255 NULLABLE
boolean is_active DEFAULT true
timestamps
```

## 32. audit_logs

```
uuid id PK
foreignUuid user_id -> users NULLABLE SET NULL
foreignUuid vendor_id -> vendors NULLABLE SET NULL
string auditable_type 255 NOT NULL
uuid auditable_id NOT NULL
string action 30 NOT NULL               -- created, updated, deleted, viewed, downloaded, opened, sealed, approved, rejected, login, logout
json old_values NULLABLE
json new_values NULLABLE
string ip_address 45 NULLABLE
string user_agent 500 NULLABLE
timestamp created_at

INDEX(auditable_type, auditable_id)
INDEX(user_id, created_at)
INDEX(created_at)
```

## 33. document_access_logs

```
uuid id PK
foreignUuid user_id -> users NULLABLE SET NULL
foreignUuid vendor_id -> vendors NULLABLE SET NULL
string document_type 255 NOT NULL
uuid document_id NOT NULL
string action 30 NOT NULL               -- viewed, downloaded, printed
string ip_address 45 NULLABLE
string user_agent 500 NULLABLE
timestamp accessed_at DEFAULT now

INDEX(document_type, document_id)
```

## 34. activity_logs

```
uuid id PK
foreignUuid user_id -> users NULLABLE SET NULL
foreignUuid vendor_id -> vendors NULLABLE SET NULL
string description 500 NOT NULL
string subject_type 255 NULLABLE
uuid subject_id NULLABLE
json properties NULLABLE
timestamp created_at
```

## 35. system_settings

```
uuid id PK
string key 255 NOT NULL UNIQUE
text value NOT NULL
string group 100 NOT NULL               -- general, notifications, approvals, security, display
string type 30 DEFAULT 'string'         -- string, integer, boolean, json
text description NULLABLE
timestamp updated_at
foreignUuid updated_by -> users NULLABLE SET NULL
```
