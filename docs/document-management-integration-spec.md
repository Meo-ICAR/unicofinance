# UnicoFinance — Document Management Integration Specification

> Specification for integrating the BPM application with the separate Laravel 13 + Filament 5.4 document-management application that shares the same MySQL server and manages documents, classifications, and document metadata.

---

## Document Metadata

- **Document version:** 1.0
- **Last updated:** 2026-04-11
- **Applies to:** external document-management application
- **Framework target:** Laravel 13
- **Admin target:** Filament 5.4
- **Media layer:** Spatie Media Library
- **Database topology:** shared MySQL server with logical application separation

---

## Purpose

This specification describes the external document-management application that works alongside the BPM platform.

The external app is responsible for:

- document storage and metadata management
- document classification
- regex-based first-pass recognition
- AI-assisted classification and enrichment
- document verification workflow
- management of expiration, signature, and document validity states
- document attachment to domain entities through a polymorphic relationship

The BPM app and the document-management app are separate Laravel applications, but they operate on the same MySQL server and share compatible business concepts.

---

## Integration Context

### Architectural Relationship

There are two related systems:

1. **BPM / SOP application**
    - manages processes, tasks, RACI, execution, compliance workflows
2. **Document-management application**
    - manages documents, classifications, extracted text, AI metadata, status lifecycle, and storage-related concerns

### Shared Business Goal

Together, the two systems enable:

- document-driven process execution
- compliance and privacy evidence management
- automated document classification
- document verification states
- linking required document types to process steps and runtime activities

### Important Boundary

The document-management app is the **source of truth for documents and their classification lifecycle**.

The BPM app is the **source of truth for process orchestration and operational execution**.

---

## Technology Assumptions for the External App

| Layer                   | Technology                                                    |
| ----------------------- | ------------------------------------------------------------- |
| Framework               | Laravel 13                                                    |
| Admin Panel             | Filament 5.4                                                  |
| Media Management        | Spatie Media Library                                          |
| Database                | MySQL                                                         |
| Audit / activity        | application-specific logging and/or Filament activity logging |
| AI support              | document text extraction + AI classification/enrichment       |
| Classification strategy | regex first, AI second                                        |

---

## Core Concepts

### 1. Document Type

The core classification table is `document_types`.

It defines:

- what kind of document the system recognizes
- which business context it belongs to
- whether it applies to people, companies, agents, practices, etc.
- whether the document is monitored for expiration
- whether it should be signed
- whether it is sensitive
- whether AI abstraction or AI conformity checks are required
- which regex or AI pattern can classify it

`document_types` is a **global lookup table without tenant ownership**.

### 2. Document Status

The state of a document from an operational verification point of view is modeled in `document_status`.

This defines whether a document is:

- missing
- pending review
- under verification
- accepted
- rejected
- expired
- cancelled
- waiting for additional information

This is not just UI decoration; it is the canonical verification vocabulary for documents.

### 3. Document

The `documents` table stores the real document record and its metadata.

It includes:

- polymorphic ownership via `documentable_type` and `documentable_id`
- company linkage where relevant
- document classification
- verification metadata
- extracted text
- AI summary / abstract
- AI confidence score
- synchronization metadata
- expiration and issue dates
- signature metadata
- descriptive annotations

---

## Classification Pipeline

The classification process is intentionally multi-step.

### Step 1 — Acquisition

When a document is acquired, uploaded, synced, or discovered:

- a `documents` record is created
- the document is linked to a polymorphic owner
- initial metadata is stored
- media storage is handled through Spatie Media Library or compatible storage logic

### Step 2 — Regex Classification

A regex-based pass attempts to identify the document type using:

- file name
- extracted text
- structured metadata
- `document_types.regex` or `document_types.regex_pattern`

Regex matching is the first-pass classifier because it is:

- deterministic
- fast
- explainable
- low-cost

### Step 3 — AI Classification

If regex is insufficient, ambiguous, or low-confidence:

- AI is used to infer the likely `document_type`
- AI may also generate:
    - `ai_abstract`
    - structured `metadata`
    - conformity or semantic hints

The fields supporting this include:

- `document_types.is_AiAbstract`
- `document_types.is_AiCheck`
- `document_types.AiPattern`
- `documents.ai_abstract`
- `documents.ai_confidence_score`
- `documents.metadata`
- `documents.extracted_text`

### Step 4 — Human Verification

A user or staff member may then:

- review the classification
- verify correctness
- reject the document
- request additional information
- confirm the final verification state

---

## Status Model

## `document_status`

The verification states are defined in `document_status`.

### Known statuses

| Status           | Meaning                           | is_ok | is_rejected |
| ---------------- | --------------------------------- | ----- | ----------- |
| `ASSENTE`        | Document not yet uploaded         | 0     | 0           |
| `DA VERIFICARE`  | Uploaded and waiting for review   | 0     | 0           |
| `IN VERIFICA`    | Being reviewed                    | 0     | 0           |
| `OK`             | Verified and valid                | 1     | 0           |
| `DIFFORME`       | Non-conforming / anomalous        | 0     | 1           |
| `RICHIESTA INFO` | More information required         | 0     | 0           |
| `ERRATO`         | Wrong document                    | 0     | 1           |
| `ANNULLATO`      | Cancelled and must be re-uploaded | 0     | 1           |
| `SCADUTO`        | Expired                           | 0     | 1           |

### Design Notes

- `document_status` is a lookup/reference table
- `is_ok` and `is_rejected` provide semantic grouping
- applications should prefer the canonical status vocabulary over free-text state naming

---

## Global Classification Model

## `document_types`

`document_types` is the primary classification vocabulary.

### Architectural Role

It is a **global, non-tenant table** that defines all recognized document categories.

This is critical because:

- classification consistency must be shared
- regex and AI patterns should be reusable across tenants
- document requirements can map to the same normalized type system

### Key Fields

| Field                     | Meaning                                   |
| ------------------------- | ----------------------------------------- |
| `name`                    | Human-readable document type              |
| `description`             | Additional explanation                    |
| `code`                    | Mnemonic unique-like business code        |
| `codegroup`               | Logical grouping of similar documents     |
| `slug`                    | Stable unique identifier                  |
| `regex_pattern` / `regex` | First-pass classification pattern         |
| `priority`                | Matching or display priority              |
| `phase`                   | Process phase or business phase           |
| `is_person`               | Related to a person                       |
| `is_company`              | Related to a company                      |
| `is_employee`             | Related to an employee                    |
| `is_agent`                | Related to an agent                       |
| `is_principal`            | Related to a principal                    |
| `is_client`               | Related to a client                       |
| `is_practice`             | Related to a practice                     |
| `is_signed`               | Signature required                        |
| `is_monitored`            | Expiration or ongoing validity monitored  |
| `duration`                | Validity duration in days                 |
| `emitted_by`              | Issuing authority                         |
| `is_sensible`             | Sensitive data indicator                  |
| `is_template`             | Document is system-provided               |
| `is_stored`               | Long-term/substitutive retention required |
| `is_endmonth`             | Expiration approximated to end of month   |
| `is_AiAbstract`           | AI should produce abstract                |
| `is_AiCheck`              | AI conformity check required              |
| `AiPattern`               | AI hint for identifying this type         |

### Example Families in the Dataset

The provided data includes classes such as:

- identity documents
- tax/health cards
- AML and privacy forms
- transparency and regulatory documents
- OAM / IVASS evidence
- company registry documents
- invoices and compensation communications
- GDPR response/request documents
- training, curriculum, and certification files

This confirms that `document_types` is not narrow document storage metadata; it is a strategic domain classification model.

---

## Document Record Model

## `documents`

The `documents` table is the central operational record.

### Core Responsibilities

A document record must represent:

- the physical or logical document instance
- its owner through a polymorphic relationship
- its classification
- its verification state
- its extracted and AI-generated metadata
- its validity timeline
- its upload / verification lineage
- its sync state with external storage providers

### Key Columns

| Field                                   | Meaning                          |
| --------------------------------------- | -------------------------------- |
| `id`                                    | UUID primary key                 |
| `company_id`                            | optional tenant owner            |
| `documentable_type` / `documentable_id` | polymorphic owner                |
| `document_type_id`                      | normalized classification        |
| `name`                                  | document label/name              |
| `status`                                | current document lifecycle state |
| `expires_at`                            | expiration date                  |
| `emitted_at`                            | issue date                       |
| `docnumber`                             | document number                  |
| `verified_at` / `verified_by`           | verification trace               |
| `uploaded_by`                           | uploader                         |
| `rejection_note`                        | reason for rejection             |
| `annotation`                            | operator notes                   |
| `description`                           | descriptive metadata             |
| `url_document`                          | publication/source URL           |
| `ai_abstract`                           | AI-generated summary             |
| `ai_confidence_score`                   | confidence 0-100                 |
| `extracted_text`                        | OCR/PDF extracted raw text       |
| `metadata`                              | structured extracted metadata    |
| `is_signed`                             | signature present                |
| `collection`                            | Spatie media collection          |
| `is_unique`                             | unique document in collection    |
| `sharepoint_*`                          | external file sync metadata      |
| `sync_status`                           | sync lifecycle                   |
| `file_hash`                             | duplicate detection              |

### Relationship Notes

- the owner is polymorphic
- `document_type_id` links to the global classification table
- `company_id` is nullable because not every use case may require tenant ownership directly
- `verified_by`, `uploaded_by`, and `user_id` link document behavior to users
- soft-deletes are enabled via `deleted_at`

---

## Ownership Model

Documents are attached through a polymorphic relation:

- `documentable_type`
- `documentable_id`

### Expected Owners

From the current data and intended use, documents may belong to:

- `Company`
- `Client`
- `Employee`
- `Agent`
- `Practice`
- other future domain models

This means the document app should treat document ownership as a reusable capability, not as a client-only or employee-only feature.

---

## Multi-Tenancy Rules

The external app is multi-tenant-aware, but not every table is tenant-owned.

### Tenant-Owned Data

Tables with `company_id` represent tenant-related runtime records.

Example:

- `documents`

### Global Lookup Data

Tables without `company_id` are shared lookup/reference tables and can be read by every company.

Examples:

- `document_types`
- `document_status`

### Important Rule

Do not duplicate global document classifications per company unless a later extension explicitly introduces tenant overrides.

The baseline architecture assumes:

- one shared taxonomy of document types
- one shared taxonomy of document statuses
- tenant-scoped document instances where needed

---

## Regex + AI Strategy

The recommended operational strategy is:

1. try deterministic regex classification first
2. use AI only when needed or explicitly required
3. persist both the selected document type and AI confidence/evidence
4. allow manual review to override automation

### Why This Strategy

Regex provides:

- speed
- repeatability
- easy debugging

AI provides:

- semantic fallback
- fuzzy matching on low-quality inputs
- metadata extraction
- summary generation
- conformity support

### Recommendation

The app should preserve enough evidence to explain why a classification happened:

- regex matched?
- which pattern?
- AI classification result?
- AI confidence?
- manual override?

---

## Verification Workflow

The external app should support the following verification flow:

1. document acquired
2. classification attempted
3. status moves to `DA VERIFICARE` or `IN VERIFICA`
4. operator reviews
5. operator sets:
    - `OK`
    - `DIFFORME`
    - `ERRATO`
    - `RICHIESTA INFO`
    - `SCADUTO`
    - `ANNULLATO`

### Verification Metadata

When verification occurs, persist:

- `verified_at`
- `verified_by`
- `rejection_note` if needed
- status transition
- notes/annotations

---

## Expiration and Monitoring Rules

Some document types are monitored over time.

This is driven mainly by `document_types`:

- `is_monitored`
- `duration`
- `is_endmonth`

### Expected Behavior

If a document type is monitored:

- expiration should be computed or tracked
- document validity should be surfaced operationally
- expired items should move toward `SCADUTO`
- BPM or compliance workflows may react to that state

---

## Signature and Template Semantics

Some document types are not just uploaded files.

The model also supports:

- documents that must be signed
- documents provided by the company/system as templates
- unique collection rules

Relevant fields include:

- `is_signed`
- `is_template`
- `collection`
- `is_unique`

This means the external app is both:

- a repository of collected documents
- a manager of system-provided document templates

---

## SharePoint / External Sync Semantics

The schema supports SharePoint synchronization.

Relevant fields:

- `sharepoint_id`
- `sharepoint_drive_id`
- `sharepoint_etag`
- `sync_status`

### Suggested Meaning of `sync_status`

- `local`
- `syncing`
- `synced`
- `failed`

### Integration Rule

Storage sync concerns should not replace the normalized document record. The `documents` row remains the core application-level source of truth.

---

## BPM Integration Guidance

The BPM app should integrate with this external app at the conceptual level as follows.

### 1. Required Documents per Process

A BPM process or task may require one or more `document_types`.

### 2. Runtime Evidence

A `TaskExecution` may need to validate whether required documents of specific types exist and are in an acceptable status.

### 3. Privacy/Compliance Linkage

The classification model can help infer:

- what kind of regulated data is present
- whether the document is sensitive
- whether expiry monitoring is required
- whether the document satisfies a compliance gate

### 4. Trigger Opportunities

Possible BPM triggers include:

- document uploaded
- document classified
- document verified as `OK`
- document marked `DIFFORME`
- document expired
- additional information requested

---

## Design Recommendations for the New App

### Recommended Domain Separation

Separate the new app into these concerns:

1. **document taxonomy**
    - `document_types`
    - `document_status`
2. **document lifecycle**
    - upload, verification, expiration, rejection, sync
3. **classification pipeline**
    - regex matching
    - AI matching
    - manual override
4. **document ownership**
    - polymorphic attachment
5. **storage/integration**
    - Spatie media
    - SharePoint sync
6. **BPM/compliance integration**
    - status gates
    - required-document checks
    - process triggers

### Recommended Services

- `DocumentClassifierService`
- `RegexDocumentClassifier`
- `AiDocumentClassifier`
- `DocumentVerificationService`
- `DocumentExpiryService`
- `DocumentSyncService`
- `DocumentTypeResolverService`

### Recommended Events

- `DocumentUploaded`
- `DocumentClassified`
- `DocumentClassificationFailed`
- `DocumentVerified`
- `DocumentRejected`
- `DocumentExpired`
- `DocumentSyncFailed`

---

## Architectural Decisions and Tradeoffs

### Why Shared `document_types`

Because classification vocabulary should be normalized across tenants and workflows.

### Why Regex First

Because it is cheap, deterministic, explainable, and robust for many formal document names.

### Why AI Second

Because some documents are ambiguous, low-quality, or not classifiable by regex alone.

### Why Polymorphic Ownership

Because many business entities need document attachment, not just one.

### Why Separate App

Because document management has its own lifecycle, metadata model, storage concerns, and classification pipeline that would otherwise overload the BPM app.

### Why Shared MySQL Server

Because both apps need interoperable business data, but can still remain logically separated by application boundaries.

---

## Minimal Implementation Contract for the New App

The new app should assume:

- Laravel 13
- Filament 5.4
- Spatie Media Library
- same MySQL server as the BPM app
- `document_types` as the canonical classification lookup table
- `document_status` as the canonical verification-state lookup table
- `documents` as the core document lifecycle table
- regex-first and AI-second classification
- polymorphic document ownership
- support for expiration, signature, sync, and verification metadata

---

## Summary

The external document-management application is a **classification-centric, metadata-rich, multi-entity document platform** that complements the BPM application.

Its core responsibilities are:

- storing documents
- classifying them using `document_types`
- tracking lifecycle and validity through `documents` and `document_status`
- enriching records with extracted text and AI output
- managing verification and expiration
- acting as the system of record for document evidence used by BPM and compliance workflows
