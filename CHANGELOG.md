# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.5.1 Under development

- docs: describe the automatic debugger wiring `yii2-extensions/debug` and `yii3/debug` provide for this package.
- docs: describe the explicit debugger registration, `yii3/inertia` config groups, and Yii2 `dispatchers` recipe in `docs/debugging.md`.
- build(deps): require `php-forge/debug` `^0.4`.

## 0.5.0 September 18, 2026

- build!: require `php-forge/debug` `^0.3`.

## 0.4.0 September 16, 2026

- refactor(debug)!: consume the php-forge/debug `0.2` presenter value objects.

## 0.3.0 September 11, 2026

- feat!: provide a declarative Inertia debugger panel through `php-forge/debug`.

## 0.2.1 September 05, 2026

- feat: add a portable resolved-page observer that forwards page payloads and shared-prop keys to callbacks.

## 0.2.0 August 25, 2026

- docs: add `Next steps` section with links to installation, usage, configuration, and testing guides.
- feat!: replace long `PageInput`, `Page`, `MergeProp`, and resolution constructors with immutable modifiers, typed getters, and `PageMetadata`.
- feat: add `PageInput::create()` and `Protocol::create()` construction shortcuts while retaining public constructors.

## 0.1.0 August 24, 2026

- feat: added a framework-agnostic Inertia.js protocol core under the `PHPForge\Inertia` namespace.
- docs: add class-level PHPDoc for the migrated protocol, page, prop, result, and support APIs.
- test: achieve 100% class, method, and line coverage through public behavior with explicit invariant exclusions.
