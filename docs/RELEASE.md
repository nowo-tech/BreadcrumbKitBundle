# Release process

1. Ensure `main` is green: CI passes on GitHub Actions.
2. Run a full local check: `make release-check` (Docker) or `composer qa`, `composer phpstan`, and `composer test-coverage`.
3. Complete the **Release security checklist (12.4.1)** in `docs/SECURITY.md`.
4. Update `docs/CHANGELOG.md`: move items from **Unreleased** to a dated section with the new version.
5. Commit changelog (and any version bumps in `composer.json` if you version the package explicitly in-repo).
6. Create an annotated Git tag: `git tag -a vX.Y.Z -m "Release vX.Y.Z"`.
7. Push the tag: `git push origin vX.Y.Z`. The `release.yml` workflow creates the GitHub Release.

For historical tags without releases, maintainers may run the `sync-releases.yml` workflow manually.

After creating the release commit and tag, run `make check-no-cursor-coauthor` again **before** `git push` (REQ-GIT-001). The release commit itself is not covered by an earlier `release-check` run.
