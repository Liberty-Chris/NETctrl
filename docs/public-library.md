# Complete public session library

Version 0.9.0-beta.3 replaces the five session cutoff with pages of 20 closed sessions. Live sessions appear on every page. Older and newer navigation works without JavaScript. Polling keeps the selected page and clamps it when the number of pages changes.

Ordering uses closed time, start time and ID to avoid unstable ordering when timestamps match. Database reads are bounded to at most 50 closed sessions per request. There is no database migration and no change to operator permissions, write routes or public session fields.

Run `php tests/public-library.php` from the repository root. The test covers traversal of every closed session, stable ordering, bounded and invalid page requests, live sessions and an empty library.
