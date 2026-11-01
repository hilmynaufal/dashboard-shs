# Kamus skema MySQL

Dihasilkan oleh `php artisan app:document-schema` dari metadata skema yang benar-benar dimigrasikan.

Tidak memuat baris data, kredensial, nama database lingkungan, atau identitas nyata.

## account_codes

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| code | varchar(60) | Tidak |  |
| name | varchar(255) | Tidak |  |
| is_active | tinyint(1) | Tidak |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `code`.
- Unik: `id`.

## activity_logs

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| actor_id | bigint unsigned | Ya | FK |
| action | varchar(100) | Tidak |  |
| subject_type | varchar(100) | Tidak |  |
| subject_id | bigint unsigned | Tidak |  |
| before_values | json | Ya |  |
| after_values | json | Ya |  |
| reason | text | Ya |  |
| occurred_at | datetime | Tidak |  |

- Unik: `id`.
- Relasi `actor_id` → `users(id)`, hapus: restrict.

## assignment_items

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| review_assignment_id | bigint unsigned | Tidak | FK |
| proposal_item_id | bigint unsigned | Tidak | FK |
| initial_version_id | bigint unsigned | Tidak | FK |
| examiner_member_id | bigint unsigned | Tidak | FK |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `id, proposal_item_id`.
- Unik: `review_assignment_id, proposal_item_id`.
- Unik: `id`.
- Relasi `review_assignment_id, examiner_member_id` → `assignment_members(review_assignment_id, id)`, hapus: restrict.
- Relasi `proposal_item_id, initial_version_id` → `proposal_item_versions(proposal_item_id, id)`, hapus: restrict.
- Relasi `proposal_item_id` → `proposal_items(id)`, hapus: restrict.
- Relasi `review_assignment_id` → `review_assignments(id)`, hapus: restrict.

## assignment_members

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| review_assignment_id | bigint unsigned | Tidak | FK |
| user_id | bigint unsigned | Tidak | FK |
| role | enum('examiner','team_lead','technical_controller') | Tidak |  |
| is_active | tinyint(1) | Tidak |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `review_assignment_id, id`.
- Unik: `review_assignment_id, user_id, role`.
- Unik: `id`.
- Relasi `review_assignment_id` → `review_assignments(id)`, hapus: restrict.
- Relasi `user_id` → `users(id)`, hapus: restrict.

## assignment_transfers

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| assignment_item_id | bigint unsigned | Tidak | FK |
| from_member_id | bigint unsigned | Tidak | FK |
| to_member_id | bigint unsigned | Tidak | FK |
| transferred_by | bigint unsigned | Tidak | FK |
| reason | text | Tidak |  |
| transferred_at | datetime | Tidak |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `id`.
- Relasi `assignment_item_id` → `assignment_items(id)`, hapus: restrict.
- Relasi `from_member_id` → `assignment_members(id)`, hapus: restrict.
- Relasi `to_member_id` → `assignment_members(id)`, hapus: restrict.
- Relasi `transferred_by` → `users(id)`, hapus: restrict.

## cache

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| key | varchar(255) | Tidak | PK |
| value | mediumtext | Tidak |  |
| expiration | bigint | Tidak |  |

- Unik: `key`.

## cache_locks

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| key | varchar(255) | Tidak | PK |
| owner | varchar(255) | Tidak |  |
| expiration | bigint | Tidak |  |

- Unik: `key`.

## evidence_files

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| organization_id | bigint unsigned | Tidak | FK |
| uploaded_by | bigint unsigned | Tidak | FK |
| original_name | varchar(255) | Tidak |  |
| disk | varchar(50) | Tidak |  |
| path | varchar(255) | Tidak |  |
| mime_type | varchar(100) | Tidak |  |
| byte_size | bigint unsigned | Tidak |  |
| sha256 | char(64) | Tidak |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `path`.
- Unik: `id`.
- Relasi `organization_id` → `organizations(id)`, hapus: restrict.
- Relasi `uploaded_by` → `users(id)`, hapus: restrict.

## failed_jobs

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| uuid | varchar(255) | Tidak |  |
| connection | varchar(255) | Tidak |  |
| queue | varchar(255) | Tidak |  |
| payload | longtext | Tidak |  |
| exception | longtext | Tidak |  |
| failed_at | timestamp | Tidak |  |

- Unik: `uuid`.
- Unik: `id`.

## import_batches

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| proposal_id | bigint unsigned | Tidak | FK |
| created_by | bigint unsigned | Tidak | FK |
| file_sha256 | char(64) | Tidak |  |
| sheet_name | varchar(100) | Tidak |  |
| status | enum('preview','processing','completed','failed') | Tidak |  |
| mapping | json | Ya |  |
| result_summary | json | Ya |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `proposal_id, file_sha256, sheet_name`.
- Unik: `id`.
- Relasi `created_by` → `users(id)`, hapus: restrict.
- Relasi `proposal_id` → `proposals(id)`, hapus: restrict.

## import_rows

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| import_batch_id | bigint unsigned | Tidak | FK |
| row_number | int unsigned | Tidak |  |
| raw_values | json | Tidak |  |
| errors | json | Ya |  |
| item_version_id | bigint unsigned | Ya | FK |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `import_batch_id, row_number`.
- Unik: `id`.
- Relasi `import_batch_id` → `import_batches(id)`, hapus: restrict.
- Relasi `item_version_id` → `proposal_item_versions(id)`, hapus: restrict.

## item_classifications

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| code | varchar(60) | Tidak |  |
| name | varchar(255) | Tidak |  |
| parent_id | bigint unsigned | Ya | FK |
| is_active | tinyint(1) | Tidak |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `code`.
- Unik: `id`.
- Relasi `parent_id` → `item_classifications(id)`, hapus: restrict.

## item_reviews

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| assignment_item_id | bigint unsigned | Tidak | FK |
| proposal_item_id | bigint unsigned | Tidak | FK |
| item_version_id | bigint unsigned | Tidak | FK |
| reviewed_by | bigint unsigned | Tidak | FK |
| status | enum('in_progress','awaiting_response','recheck','completed') | Tidak |  |
| comparison_price | decimal(20,2) unsigned | Ya |  |
| recommended_price | decimal(20,2) unsigned | Ya |  |
| recommendation | text | Ya |  |
| completed_at | datetime | Ya |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `id`.
- Unik: `assignment_item_id, item_version_id`.
- Relasi `assignment_item_id, proposal_item_id` → `assignment_items(id, proposal_item_id)`, hapus: restrict.
- Relasi `proposal_item_id, item_version_id` → `proposal_item_versions(proposal_item_id, id)`, hapus: restrict.
- Relasi `reviewed_by` → `users(id)`, hapus: restrict.

## item_version_accounts

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| item_version_id | bigint unsigned | Tidak | PK |
| account_code_id | bigint unsigned | Tidak | PK |
| code_snapshot | varchar(60) | Tidak |  |
| name_snapshot | varchar(255) | Tidak |  |

- Unik: `item_version_id, account_code_id`.
- Relasi `account_code_id` → `account_codes(id)`, hapus: restrict.
- Relasi `item_version_id` → `proposal_item_versions(id)`, hapus: restrict.

## item_version_evidence

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| item_version_id | bigint unsigned | Tidak | PK |
| evidence_file_id | bigint unsigned | Tidak | PK |
| purpose | varchar(100) | Tidak |  |

- Unik: `item_version_id, evidence_file_id`.
- Relasi `evidence_file_id` → `evidence_files(id)`, hapus: restrict.
- Relasi `item_version_id` → `proposal_item_versions(id)`, hapus: restrict.

## job_batches

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | varchar(255) | Tidak | PK |
| name | varchar(255) | Tidak |  |
| total_jobs | int | Tidak |  |
| pending_jobs | int | Tidak |  |
| failed_jobs | int | Tidak |  |
| failed_job_ids | longtext | Tidak |  |
| options | mediumtext | Ya |  |
| cancelled_at | int | Ya |  |
| created_at | int | Tidak |  |
| finished_at | int | Ya |  |

- Unik: `id`.

## jobs

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| queue | varchar(255) | Tidak |  |
| payload | longtext | Tidak |  |
| attempts | smallint unsigned | Tidak |  |
| reserved_at | int unsigned | Ya |  |
| available_at | int unsigned | Tidak |  |
| created_at | int unsigned | Tidak |  |

- Unik: `id`.

## kkr_approvals

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| kkr_version_id | bigint unsigned | Tidak | FK |
| decided_by | bigint unsigned | Tidak | FK |
| level | enum('team_lead','technical_controller') | Tidak |  |
| decision | enum('approved','returned') | Tidak |  |
| notes | text | Ya |  |
| decided_at | datetime | Tidak |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `kkr_version_id, level`.
- Unik: `id`.
- Relasi `decided_by` → `users(id)`, hapus: restrict.
- Relasi `kkr_version_id` → `kkr_versions(id)`, hapus: restrict.

## kkr_documents

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| review_assignment_id | bigint unsigned | Tidak | FK |
| prepared_by | bigint unsigned | Tidak | FK |
| number | varchar(100) | Tidak |  |
| title | varchar(255) | Tidak |  |
| status | enum('draft','awaiting_lead','returned','awaiting_controller','final','superseded') | Tidak |  |
| lock_version | int unsigned | Tidak |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `number`.
- Unik: `id`.
- Relasi `prepared_by` → `users(id)`, hapus: restrict.
- Relasi `review_assignment_id` → `review_assignments(id)`, hapus: restrict.

## kkr_entries

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| kkr_version_id | bigint unsigned | Tidak | FK |
| item_review_id | bigint unsigned | Tidak | FK |
| display_order | int unsigned | Tidak |  |
| has_open_notes | tinyint(1) | Tidak |  |
| snapshot | json | Tidak |  |

- Unik: `kkr_version_id, display_order`.
- Unik: `kkr_version_id, item_review_id`.
- Unik: `id`.
- Relasi `item_review_id` → `item_reviews(id)`, hapus: restrict.
- Relasi `kkr_version_id` → `kkr_versions(id)`, hapus: restrict.

## kkr_exports

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| kkr_version_id | bigint unsigned | Tidak | FK |
| generated_by | bigint unsigned | Tidak | FK |
| disk | varchar(50) | Tidak |  |
| path | varchar(255) | Tidak |  |
| sha256 | char(64) | Tidak |  |
| byte_size | bigint unsigned | Tidak |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `path`.
- Unik: `id`.
- Relasi `generated_by` → `users(id)`, hapus: restrict.
- Relasi `kkr_version_id` → `kkr_versions(id)`, hapus: restrict.

## kkr_versions

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| kkr_document_id | bigint unsigned | Tidak | FK |
| version_number | int unsigned | Tidak |  |
| template_version | varchar(80) | Tidak |  |
| snapshot | json | Tidak |  |
| snapshot_sha256 | char(64) | Tidak |  |
| created_by | bigint unsigned | Tidak | FK |
| supersedes_version_id | bigint unsigned | Ya | FK |
| change_reason | text | Ya |  |
| submitted_at | datetime | Ya |  |
| finalized_at | datetime | Ya |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `kkr_document_id, version_number`.
- Unik: `id`.
- Relasi `created_by` → `users(id)`, hapus: restrict.
- Relasi `kkr_document_id` → `kkr_documents(id)`, hapus: restrict.
- Relasi `supersedes_version_id` → `kkr_versions(id)`, hapus: restrict.

## migrations

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | int unsigned | Tidak | PK |
| migration | varchar(255) | Tidak |  |
| batch | int | Tidak |  |

- Unik: `id`.

## note_responses

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| review_note_id | bigint unsigned | Tidak | FK |
| responded_by | bigint unsigned | Tidak | FK |
| response | text | Tidak |  |
| revised_version_id | bigint unsigned | Ya | FK |
| responded_at | datetime | Tidak |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `id`.
- Relasi `responded_by` → `users(id)`, hapus: restrict.
- Relasi `review_note_id` → `review_notes(id)`, hapus: restrict.
- Relasi `revised_version_id` → `proposal_item_versions(id)`, hapus: restrict.

## organizations

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| code | varchar(50) | Tidak |  |
| name | varchar(255) | Tidak |  |
| is_active | tinyint(1) | Tidak |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `code`.
- Unik: `id`.

## password_reset_tokens

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| email | varchar(255) | Tidak | PK |
| token | varchar(255) | Tidak |  |
| created_at | timestamp | Ya |  |

- Unik: `email`.

## permission_role

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| permission_id | bigint unsigned | Tidak | PK |
| role_id | bigint unsigned | Tidak | PK |

- Unik: `permission_id, role_id`.
- Relasi `permission_id` → `permissions(id)`, hapus: restrict.
- Relasi `role_id` → `roles(id)`, hapus: restrict.

## permissions

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| code | varchar(100) | Tidak |  |
| name | varchar(255) | Tidak |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `code`.
- Unik: `id`.

## price_sources

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| item_version_id | bigint unsigned | Tidak | FK |
| type | enum('survey','regulation','study','other') | Tidak |  |
| name | varchar(255) | Tidak |  |
| source_location | text | Ya |  |
| source_date | date | Ya |  |
| reference_document_id | bigint unsigned | Ya | FK |
| reference_snapshot | json | Ya |  |
| unit_description | varchar(255) | Ya |  |
| specification | text | Ya |  |
| base_price | decimal(20,2) unsigned | Ya |  |
| tax_amount | decimal(20,2) unsigned | Ya |  |
| shipping_amount | decimal(20,2) unsigned | Ya |  |
| other_amount | decimal(20,2) unsigned | Ya |  |
| total_price | decimal(20,2) unsigned | Ya |  |
| tax_status | enum('included','excluded','unknown') | Tidak |  |
| calculation_notes | text | Ya |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `id`.
- Relasi `item_version_id` → `proposal_item_versions(id)`, hapus: restrict.
- Relasi `reference_document_id` → `reference_documents(id)`, hapus: restrict.

## proposal_item_versions

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| proposal_item_id | bigint unsigned | Tidak | FK |
| version_number | int unsigned | Tidak |  |
| item_classification_id | bigint unsigned | Tidak | FK |
| unit_id | bigint unsigned | Tidak | FK |
| description | varchar(255) | Tidak |  |
| specification | text | Tidak |  |
| proposed_price | decimal(20,2) unsigned | Tidak |  |
| tkdn_percent | decimal(5,2) unsigned | Ya |  |
| origin | enum('new','existing') | Ya |  |
| justification | text | Ya |  |
| reference_snapshot | json | Tidak |  |
| created_by | bigint unsigned | Tidak | FK |
| change_reason | text | Ya |  |
| submitted_at | datetime | Ya |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `proposal_item_id, id`.
- Unik: `proposal_item_id, version_number`.
- Unik: `id`.
- Relasi `created_by` → `users(id)`, hapus: restrict.
- Relasi `item_classification_id` → `item_classifications(id)`, hapus: restrict.
- Relasi `proposal_item_id` → `proposal_items(id)`, hapus: restrict.
- Relasi `unit_id` → `units(id)`, hapus: restrict.

## proposal_items

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| public_id | char(36) | Tidak |  |
| proposal_id | bigint unsigned | Tidak | FK |
| status | enum('draft','submitted','needs_completion','ready','assigned','reviewing','awaiting_response','recheck','reviewed') | Tidak |  |
| lock_version | int unsigned | Tidak |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `id`.
- Unik: `public_id`.
- Relasi `proposal_id` → `proposals(id)`, hapus: restrict.

## proposals

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| organization_id | bigint unsigned | Tidak | FK |
| submission_period_id | bigint unsigned | Tidak | FK |
| type | enum('SHS','SBU') | Tidak |  |
| number | varchar(100) | Ya |  |
| title | varchar(255) | Tidak |  |
| status | enum('draft','submitted','verification','review','completed') | Tidak |  |
| created_by | bigint unsigned | Tidak | FK |
| submitted_at | datetime | Ya |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `id`.
- Unik: `number`.
- Relasi `created_by` → `users(id)`, hapus: restrict.
- Relasi `organization_id` → `organizations(id)`, hapus: restrict.
- Relasi `submission_period_id` → `submission_periods(id)`, hapus: restrict.

## reference_documents

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| title | varchar(255) | Tidak |  |
| number | varchar(255) | Ya |  |
| reference_version | varchar(80) | Tidak |  |
| effective_from | date | Ya |  |
| effective_until | date | Ya |  |
| budget_year | smallint unsigned | Ya |  |
| description | text | Ya |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `id`.

## review_aspects

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| code | varchar(60) | Tidak |  |
| name | varchar(255) | Tidak |  |
| is_required | tinyint(1) | Tidak |  |
| is_active | tinyint(1) | Tidak |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `id`.
- Unik: `code`.

## review_assignments

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| number | varchar(100) | Tidak |  |
| title | varchar(255) | Tidak |  |
| submission_period_id | bigint unsigned | Tidak | FK |
| object_organization_id | bigint unsigned | Tidak | FK |
| created_by | bigint unsigned | Tidak | FK |
| task_basis | text | Ya |  |
| scope_description | text | Ya |  |
| status | enum('draft','active','completed') | Tidak |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `id`.
- Unik: `number`.
- Relasi `created_by` → `users(id)`, hapus: restrict.
- Relasi `object_organization_id` → `organizations(id)`, hapus: restrict.
- Relasi `submission_period_id` → `submission_periods(id)`, hapus: restrict.

## review_notes

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| item_version_id | bigint unsigned | Tidak | FK |
| item_review_id | bigint unsigned | Ya | FK |
| created_by | bigint unsigned | Tidak | FK |
| type | enum('administrative','substantive') | Tidak |  |
| category | varchar(100) | Tidak |  |
| description | text | Tidak |  |
| recommendation | text | Ya |  |
| status | enum('internal','awaiting_lead','sent','responded','resolved','open_at_final') | Tidak |  |
| sent_by | bigint unsigned | Ya | FK |
| sent_at | datetime | Ya |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `id`.
- Relasi `created_by` → `users(id)`, hapus: restrict.
- Relasi `item_review_id` → `item_reviews(id)`, hapus: restrict.
- Relasi `item_version_id` → `proposal_item_versions(id)`, hapus: restrict.
- Relasi `sent_by` → `users(id)`, hapus: restrict.

## review_results

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| item_review_id | bigint unsigned | Tidak | FK |
| review_aspect_id | bigint unsigned | Tidak | FK |
| result | enum('not_assessed','compliant','non_compliant','insufficient_evidence','not_applicable') | Tidak |  |
| criterion_snapshot | json | Tidak |  |
| reason | text | Ya |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `id`.
- Unik: `item_review_id, review_aspect_id`.
- Relasi `item_review_id` → `item_reviews(id)`, hapus: restrict.
- Relasi `review_aspect_id` → `review_aspects(id)`, hapus: restrict.

## role_user

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| role_id | bigint unsigned | Tidak | PK |
| user_id | bigint unsigned | Tidak | PK |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `role_id, user_id`.
- Relasi `role_id` → `roles(id)`, hapus: restrict.
- Relasi `user_id` → `users(id)`, hapus: restrict.

## roles

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| code | varchar(50) | Tidak |  |
| name | varchar(255) | Tidak |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `id`.
- Unik: `code`.

## sessions

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | varchar(255) | Tidak | PK |
| user_id | bigint unsigned | Ya |  |
| ip_address | varchar(45) | Ya |  |
| user_agent | text | Ya |  |
| payload | longtext | Tidak |  |
| last_activity | int | Tidak |  |

- Unik: `id`.

## submission_periods

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| budget_year | smallint unsigned | Tidak |  |
| name | varchar(255) | Tidak |  |
| opens_at | datetime | Tidak |  |
| closes_at | datetime | Tidak |  |
| status | enum('draft','open','closed') | Tidak |  |
| created_by | bigint unsigned | Tidak | FK |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `id`.
- Relasi `created_by` → `users(id)`, hapus: restrict.

## units

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| code | varchar(60) | Tidak |  |
| name | varchar(255) | Tidak |  |
| is_active | tinyint(1) | Tidak |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `id`.
- Unik: `code`.

## users

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| name | varchar(255) | Tidak |  |
| email | varchar(255) | Tidak |  |
| email_verified_at | timestamp | Ya |  |
| password | varchar(255) | Tidak |  |
| remember_token | varchar(100) | Ya |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |
| organization_id | bigint unsigned | Ya | FK |
| is_active | tinyint(1) | Tidak |  |
| position | varchar(255) | Ya |  |

- Unik: `id`.
- Unik: `email`.
- Relasi `organization_id` → `organizations(id)`, hapus: restrict.

## verification_results

| Kolom | Tipe MySQL | Boleh kosong | Kunci |
| --- | --- | --- | --- |
| id | bigint unsigned | Tidak | PK |
| item_version_id | bigint unsigned | Tidak | FK |
| verified_by | bigint unsigned | Tidak | FK |
| result | enum('ready','needs_completion') | Tidak |  |
| reason | text | Ya |  |
| verified_at | datetime | Tidak |  |
| created_at | timestamp | Ya |  |
| updated_at | timestamp | Ya |  |

- Unik: `id`.
- Relasi `item_version_id` → `proposal_item_versions(id)`, hapus: restrict.
- Relasi `verified_by` → `users(id)`, hapus: restrict.

