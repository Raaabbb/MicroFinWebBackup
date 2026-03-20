## Plan: Two-Plan Realignment (Starter + Enterprise)

Replace the current five-plan model with two plans system-wide (Starter at ₱4,999 and Enterprise at ₱14,999), remove non-real feature claims from marketing and forms, and keep overage as policy text only (no charging logic yet). This avoids misrepresentation while keeping the release low-risk and executable.

**Steps**
1. Phase 1: Inventory and freeze current plan touchpoints
2. Confirm all plan references in public UI, admin UI, backend provisioning, and docs are captured before edits. This prevents orphaned references to Growth, Pro, and Unlimited.
3. Dependency: blocks all later phases.

4. Phase 2: Public website and contact form realignment
5. Update pricing section content to only Starter and Enterprise with realistic, currently supported feature language.
6. Remove non-real or unimplemented claims (for example dedicated manager or white-glove onboarding unless operationally true).
7. Remove Choose Your Plan CTA where still present.
8. Convert contact form plan input to card-based UI (not radio behavior), with one hidden value target and click-to-select card interaction, while preserving form submission name plan_tier.
9. Dependency: can run in parallel with Phase 3 after touchpoint inventory is complete.

10. Phase 3: Backend tier model consolidation
11. Replace five-tier pricing maps with two-tier map in tenant provisioning and update fallback/default plan handling.
12. Add strict server-side validation so plan_tier only accepts Starter or Enterprise.
13. Add migration mapping for existing tenants:
14. Growth -> Enterprise
15. Pro -> Enterprise
16. Unlimited -> Enterprise
17. Keep Starter as Starter
18. Recompute MRR for mapped tenants based on final two-tier prices.
19. Ensure null/empty plan from demo intake is normalized to Starter or rejected based on desired policy.
20. Dependency: required before release; blocks Phase 5 verification.

21. Phase 4: Documentation and policy alignment
22. Rewrite schema comments and pricing/feature docs to two-plan model only.
23. Document overage as policy text only, explicitly marked non-automated for now.
24. Add a truth-based feature matrix: only features currently implemented and enforceable should appear as included.
25. Dependency: can run parallel with late Phase 3, must finish before sign-off.

26. Phase 5: Validation and regression checks
27. Verify all form submissions, tenant creation flows, and admin edits work with only Starter/Enterprise values.
28. Validate no dead UI states (removed plans still referenced in selects, filters, badges, stats, or JS).
29. Run PHP lint and targeted functional smoke tests for public contact flow and super admin tenant provisioning.
30. Dependency: final gate before deployment.

**Relevant files**
- c:/xampp/htdocs/admin-draft/microfin_platform/public_website/index.php - Replace 5-card pricing section with 2-card accurate copy; remove choose-plan CTA remnants.
- c:/xampp/htdocs/admin-draft/microfin_platform/public_website/demo.php - Replace current plan selector behavior with card-based selector UI and keep submitted field plan_tier.
- c:/xampp/htdocs/admin-draft/microfin_platform/public_website/style.css - Remove stale five-plan/radio styling and add two-card selection styles.
- c:/xampp/htdocs/admin-draft/microfin_platform/super_admin/super_admin.php - Update plan pricing map and plan validation/defaults; ensure only Starter/Enterprise accepted.
- c:/xampp/htdocs/admin-draft/microfin_platform/backend/super_admin_migration.php - Add/update migration path from legacy tiers to two-tier model and MRR recalculation.
- c:/xampp/htdocs/admin-draft/microfin_platform/docs/database-schema.txt - Update tier references, limits comments, and remove obsolete five-tier examples.
- c:/xampp/htdocs/admin-draft/microfin_platform/admin_panel/admin.php - Verify plan display labels and any conditional UI logic still valid for two tiers.
- c:/xampp/htdocs/admin-draft/microfin_platform/public_website/api/api_demo.php - Validate/normalize incoming plan_tier values from contact/demo submissions.

**Verification**
1. Run PHP lint on modified PHP files and confirm zero syntax errors.
2. Submit a contact/demo request selecting Starter and then Enterprise; verify saved plan_tier values in tenants.
3. Create tenant via super admin for both plans; confirm mrr and limit defaults are correct.
4. Execute migration on a dataset containing Starter/Growth/Pro/Enterprise/Unlimited rows; verify mapped plan_tier and recomputed mrr values.
5. Search the repo for Growth|Pro|Unlimited plan labels in user-facing and provisioning paths; confirm only historical migration references remain.
6. Manually review pricing and contact pages on desktop/mobile for card selection clarity and no radio controls.

**Decisions**
- Included scope: Full system replacement to two plans only.
- Included scope: Enterprise monthly price is ₱14,999.
- Included scope: Overage is policy text only for this release.
- Excluded scope: Automated overage charging, metered billing engine, or payment gateway enforcement.
- Excluded scope: New feature development solely to satisfy marketing claims.

**Further Considerations**
1. Feature-language guardrail recommendation: Use only verifiable features in public copy, and keep aspirational capabilities in a roadmap section rather than included features.
2. Tenant migration communication recommendation: Notify existing Growth/Pro/Unlimited tenants of automatic Enterprise mapping before rollout to avoid billing disputes.
3. Backward compatibility recommendation: Keep temporary migration-safe aliases in backend input parsing for one release cycle, but never display legacy plan names in UI.