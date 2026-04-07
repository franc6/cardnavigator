# Security Policy

## Supported Versions

Only the most recent release is supported with security fixes. Do not report problems in older releases.

## Reporting a Vulnerability

**Do not open a public GitHub issue for security vulnerabilities.**

Use [GitHub's private security advisory feature](https://github.com/franc6/cardnavigator/security/advisories/new) to report vulnerabilities confidentially.

Report the actual problem, along with any exploit samples. Be clear and specific as to why this is perceived as a security problem, and how it can be exploited. If you're just reporting base functionality, such as the ability for an admin to create other admin accounts, or for an admin to modify the database, don't bother. Those aren't security problems, they're expected features, without which the application isn't very useful.

### Vulnerabilities in dependencies

Reporting these is acceptable, but do not expect a response. The odds are pretty good I'm aware of it already, and am working on a fix, have a fix, or have determined the vulnerability doesn't affect this software.

#### Non-public vulnerabilities in dependencies
If you maintain a dependency, and self-reporting ahead of public reporting, please provide the details you deem appropriate.

If you are a third-party reporting a vulnerability of a dependency that is not yet public, please do not provide any non-public details about the vulnerability. I'm not in the business of disseminating such information or determining when it should be disseminated. What I don't know, can't hurt anyone else (not by me, anyway).

## What to Expect

- **Acknowledgement:** You will receive a private acknowledgement after your report is reviewed.
- **Resolution timeline:** A fix or an explicit "won't fix" decision will be provided within **90 days** of initial response. This timeline may be reduced in the future, but this isn't a high-visibility project, and is done in my spare time.
- **Credit:** The first reporter of a given vulnerability will be credited by name (or as desired by the reporter) in the release notes. Subsequent independent reports of the same issue will be acknowledged privately but will not receive public credit.
- **AI-generated reports:** Reports that appear to be fully AI-generated will be ignored without acknowledgement. I don't have time to read reports that I can't be reasonably sure are accurate and worthy of attention.
