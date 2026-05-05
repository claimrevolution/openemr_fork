# 2.1.2
Bug fix: send `serviceTypeCodes` as a JSON array (`List<string>`) instead of a comma-separated string. The ClaimRev API tightened request validation and started rejecting the old shape with HTTP 400, breaking Check Now eligibility requests. Empty configuration still asks for all benefits.

# 2.1.1
Maintenance release: apply phpcbf style fixes, rector modernization, refresh PHPStan baseline, and refactor CSV downloads + migration helpers to avoid Semgrep XSS/SQLi false positives. No functional changes.

# 2.1.0
Adds patient balance, KPI dashboard, AR aging report, denial analytics, recoupment report, eligibility sweep with calendar indicators and appointment filters, payment-advice posting, claim status dashboard with timeline, reconciliation page, and OpenEMR 7.x compatibility shims.

# 1.0.12
Added new setup helpers to stop the sftp service from interfering with the file sending service of this module.
