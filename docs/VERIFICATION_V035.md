# v0.3.5 verification report

## Executed successfully

- PHP syntax validation across all backend PHP files
- Existing v0.3 calculation test suite
- Existing v0.3 source-contract test suite
- v0.3.5 age, manifest, resolver, fallback, and identity tests
- v0.3.5 source-contract and asset-presence tests
- Portrait manifest validator
- TypeScript and Vite production build
- ZIP integrity validation

## Portrait validation result

```text
Portrait sets: 50
Complete five-stage sets: 0
Missing stage warnings: 200
Errors: 0
Manifest valid: yes
Manifest complete: no
```

The warning count is expected because the supplied concept images contain 50 different adult characters rather than five matching life-stage portraits for each character.

## MySQL integration

The existing guarded MySQL integration test remains available. It must use a dedicated database whose name ends with `_test`. The final completion report records whether it could be executed in the build environment.
