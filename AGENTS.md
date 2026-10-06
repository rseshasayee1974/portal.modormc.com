<!--
Author: ragul-onemodo
Created: 2026-10-06 11:44:13 Asia/Calcutta (UTC+05:30)
-->

# New file metadata

For every new hand-written file in this repository, include a header with:

- `Author`: the current repository Git user name from `git config user.name`.
- `Created`: the actual creation date and time in `YYYY-MM-DD HH:mm:ss` format,
  using Asia/Calcutta (UTC+05:30), with the timezone explicitly included.

Use the file format's valid comment syntax. For PHP, put the header after
`<?php`; for Vue, put an HTML comment before the first block; for Markdown,
use an HTML comment. Preserve required shebangs, declarations, and directives.
The `Author` and `Created` fields are permanent creation metadata. Never
change them when editing an existing file, and never replace the original
author with the modifier's Git user name. Do not refresh the creation timestamp
to the modification date or time.

Do not insert comments into formats that do not support them, such as JSON,
or modify generated files, lockfiles, compiled assets, or binary files to add
metadata. When a newly authored file cannot contain comments, record its path,
author, and creation timestamp in `docs/file-authorship.md`, creating that file
with its own metadata header when first needed.
