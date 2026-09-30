# PAD review and proposed indicator mapping — 29 September 2026

Status: source review and mapping; no stored indicator identities, baselines, target schedules or actual results changed by this review.

Source: `uploads/documents/resources/1760123047_68e958a765252_PROJECT_APPRAISAL_DOCUMENT.pdf`, World Bank, Somali Sustainable Fisheries Development Project (P178032). References below use the printed page numbers (PDF file page = printed page + 5). User screenshots reproduce results-framework tables on pp. 34–36. Exact methodology text is preserved in `pad-methodology-extract.txt`. Existing database definitions are paraphrases, not verbatim PAD definitions; the unchanged database snapshot is in `indicator-records-before-review.json`.

## What the PDO baseline evidence establishes

| Current ID/code | PAD framework baseline | Date | Evidence / origin |
|---|---|---|---|
| 1 / PDO1 | 2 plans | Apr 2024 | Results table p. 34; methodology p. 37 says two co-management agreements already exist. Current database baseline agrees with the table. |
| 2 / PDO1a | 2 co-management plans | Jan 2024 | Subindicator row p. 34. |
| 3 / PDO2 | 1 FMDC meeting | Nov 2023 | Results table p. 34; methodology p. 37 specifies official communiqués and quarterly reporting. |
| 4 / PDO3 | 0 communities | Jan 2024 | Results table p. 34; methodology p. 38 describes the combined interventions at each selected location. |
| 5 / PDO3a | 0 qualifying communities | Jan 2024 | Subindicator row p. 34. Unit is Number, despite the 25% criterion in its name. |
| 6 / PDO4 | 0 direct beneficiaries | Jan 2024 | Results table p. 34; methodology p. 38 lists participants, memberships and infrastructure-user surveys. |
| 7 / PDO4a | 0% women beneficiaries | No separate date printed on sub-row | Results table p. 34. Do not invent a date. |
| 8 / PDO4b | 0 practitioners | Jan 2024 | Results table p. 34. |
| 9 / PDO5 | 0% reduction | Jan 2024 | Results table pp. 34–35. This is the starting value of the reduction indicator, NOT a measured fish-loss rate. Current database NULL represents the missing underlying loss measurement and conflates two different baseline concepts. |

PDO5's measured loss baseline is NOT supplied in the PAD. The Monitoring & Evaluation Plan on p. 38 explicitly says a PPA study has been launched to establish it, with repeat studies at mid-term and closing. The 25–40% range is an estimate, not that study's result. The 2% June 2027 and 5% June 2030 figures are reduction targets. None of 0, 2, 5, 25, 40, or an average of the range is a permissible replacement for the measured loss baseline.

Required evidence: obtain the completed PPA/year-one food-loss-and-waste assessment, or carry out the missing baseline assessment, measuring loss and waste BY WEIGHT across the targeted value chains from catch to distribution using the stated FAO methodology. Record its geography/value chains, reference period, sampling/methodology, denominator or throughput, units, baseline result, and report citation. Follow-up measurements must use a comparable scope and method. A relative reduction can then be derived from comparable baseline and follow-up loss levels, or an already calculated reduction percentage can be entered with the study that verifies it. A 0% reduction starting point cannot be a denominator in a relative-reduction calculation. The software should distinguish the framework's 0% baseline from the separately measured loss baseline.

No additional missing numerical baseline is evident for PDO1–PDO4 and their listed subindicators. Their PAD baseline values are documented framework figures; that does not establish that the portal holds their original primary evidence. For IR, IR9 and IR11 explicitly have 'No data' baselines (p. 36). IR9's methodology explains no prior patrolling (p. 41). IR3's 0% increase baseline is not a starting USD/kg sale-price measurement; calculating increases from raw prices needs comparable baseline price and inflation treatment (p. 39).

## IR mapping before any changes to stored results

The PAD does not assign the local IR1–IR11 identifiers shown by the application. Keep the existing IDs and codes and order them by the sequence in the PAD, with genuine child indicators nested. All 13 definitions exist; IR2 is present as ID 11. Each currently has six period records, FY25–FY30, with no actual values in the audited database. Repeated codes across different years are NOT duplicate indicator definitions. Lexicographic sorting explains IR1, IR10, IR11 appearing before IR2.

The proposed display names below reproduce the results table wording (pp. 35–36), with units stored separately. Differences between results-table and methodology headings must be retained as source variants, not silently normalized. No record needs merging, deletion, renumbering or re-keying.

| Database ID | Current → proposed code | Proposed exact results-table name | Classification / source |
|---|---|---|---|
| 10 | IR1 → IR1 | Climate resilient infrastructure built or rehabilitated and operational | Main; p. 35; definition p. 39 uses 'Climate informed' in its heading. |
| 11 | IR2 → IR2 | Targeted MSMEs benefitting from implementation of value chain improvement plans | Main; p. 35; definition p. 39. |
| 12 | IR2a → IR2a | Share of targeted MSMEs benefitted from implementation of value chain improvement plans of which owned/led by women | Child of IR2, not a duplicate; p. 35; shared methodology p. 39. |
| 13 | IR3 → IR3 | Value (USD/kg) increases from improved handling from fisheries under improved management | Main; p. 35; unit Percentage, not USD/kg; definition p. 39 contains a repeated 'from'. |
| 14 | IR4 → IR4 | Fish stocks with their status determined | Main; p. 35; definition pp. 39–40. |
| 15 | IR5 → IR5 | Co-management arrangements with at-least 10% women participation | Main; p. 35; definition p. 40. |
| 16 | IR5a → IR5a | Of which at least one woman in an executive position in its governance body | Child of IR5, not a duplicate; p. 35; shared methodology p. 40. |
| 17 | IR6 → IR6 | Patrol days | Main; p. 35; definition p. 40 explicitly says ANNUAL, at least six hours per day. |
| 18 | IR7 → IR7 | Registers of fishers, fishing vessels and licences developed and operational | Main; p. 35; definition p. 40: three registers servicing FGS and all FMS, semiannual reporting. |
| 19 | IR8 → IR8 | Fisheries policies, legal and regulatory texts adopted at FGS and FMS levels | Main; pp. 35–36; methodology p. 40 says 'and/or'. |
| 20 | IR9 → IR9 | Large scale industrial fishing vessels observed conducting serious illegal activities during patrols | Main; p. 36; definition pp. 40–41: offending vessels/all observed vessels over one year, deduplicated per observation. |
| 21 | IR10 → IR10 | Revenue generated from licensing fees annually | Main; p. 36; unit Amount(USD); definition p. 41. |
| 22 | IR11 → IR11 | Beneficiaries with rating ‘Satisfied’ or above on project interventions | Main; p. 36; definition p. 41. |

## Reporting level and target meaning

The PAD provides project results-framework targets; it does NOT provide a separate target allocation for each of the 12 sites or every state. The portal must not put a project-wide target beside a site actual and calculate apparent achievement. Geographic observations can support a separately reviewed project total, without automatic summing of beneficiary counts or averaging percentages.

| Indicators | PAD coverage / appropriate entry handling | Target/result basis |
|---|---|---|
| PDO1, PDO1a | FGS/FMS plans and local co-management; project total with optional state/site supporting observations, distinguished explicitly | Stock of plans formulated AND implemented as at checkpoint; no summing successive reports |
| PDO2 | FMDC institutional/project total; no site selection | Cumulative meeting checkpoints; quarterly reporting frequency |
| PDO3, PDO3a | Selected communities/locations; site evidence supports reviewed project total | Qualifying community count at checkpoint, not percentage for PDO3a |
| PDO4, PDO4a, PDO4b | Direct beneficiaries across activities/locations; project total with state/site evidence | Counts deduplicated across activities; women share uses beneficiary denominator |
| PDO5 | Targeted value chains in Somalia; study scope must be recorded; project total or scoped study observation | Baseline, mid-term, closing study; relative reduction, not annual increment |
| IR1, IR2, IR2a, IR3 | Facilities/MSMEs/surveys at targeted infrastructure; site/state observations support reviewed project totals | Operational stock / assisted MSMEs; IR2a share; IR3 inflation-adjusted percentage price increase |
| IR4 | Targeted fish STOCKS; project total; a stock is not equivalent to a project site | Stocks assessed at least once in project |
| IR5, IR5a | Co-management committees; local evidence supports project total | Qualifying arrangements counted once |
| IR6 | Surveillance activity; project total or state supporting activity; no arbitrary site assignment | Explicitly ANNUAL patrol days; quarterly actual must not be compared with a full annual target as if like-for-like |
| IR7 | Three registers servicing FGS and all FMS; project total | Operational registers; semiannual reporting, stock at checkpoint |
| IR8 | FGS and/or FMS legal instruments; project total or state supporting observation | Adopted instruments, stock at checkpoint |
| IR9 | Observed vessels in Somali waters; project total or a clearly scoped surveillance sample, not site-beneficiary data | Annual ratio with numerator/denominator and per-observation deduplication |
| IR10 | Foreign-vessel licence revenue for Somalia's EEZ; project total | ANNUAL USD receipts; do not cumulatively sum years when comparing a year's target |
| IR11 | Target population satisfaction survey; project total or explicitly scoped study observation | Mid-term/closing percentage, survey denominator required for aggregation |

Geographic options above are an implementation proposal informed by the methodologies, NOT PAD-provided state/site target allocations. PAD p. 24 says the M&E manual will detail approaches, tools and responsibilities. The actual manual/approved methodology is needed to finalize disaggregation and aggregation rules. The current 12-site catalog is the existing project map, with approximate coordinates, not a PAD-verified site allocation list.

## Source discrepancies requiring explicit treatment

- The results table prints June 2032 for the closing direct-beneficiary target (PDO4), unlike June 2030 for most other rows. Existing software maps it to FY30. Do not silently assert that this discrepancy is resolved.
- FMDC's first checkpoint is June 2024, unlike most first checkpoints in June 2025. Align by the printed date, not merely Period 1's column position.
- A blank period cell means no scheduled target, not zero and not an interpolated target. PDO5's 2% is June 2027, despite appearing in the first period column.
- IR9 targets rise from 10% to 20% even though the methodology describes the observed offending-vessel share. The stored inverse-ratio/lower-is-better status rule is an application choice, not a direction explicitly resolved by that table. Confirm performance interpretation before changing or presenting an authoritative traffic-light status.
- FY labels alone do not establish a fiscal calendar. Preserve explicit start/end dates and target reference dates; do not assume fiscal-year boundaries from June checkpoints.

## Safe implementation sequence

1. Separate `/me/` portal sessions, navigation, users/memberships, permissions and settings from the website admin.
2. Keep a two-person approval rule: an M&E user may have entry and approval rights, but cannot approve their own submission.
3. Show one naturally ordered row per indicator and a separate reporting history. Keep IDs stable.
4. Add explicit scope and site/period metadata without rewriting existing actuals. Enforce units in the server and form; do not compare disaggregated or period-specific actuals with unrelated project/annual targets.
5. Review this mapping and the unresolved methodology issues before changing authoritative baseline values, definitions, target schedules, or historical results.
