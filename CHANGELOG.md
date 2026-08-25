# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.2.1 Under development

## 0.2.0 August 25, 2026

- docs: add `Next steps` section with links to installation, usage, configuration, and testing guides.
- feat!: replace long `PageInput`, `Page`, `MergeProp`, and resolution constructors with immutable modifiers, typed getters, and `PageMetadata`.
- feat: add `PageInput::create()` and `Protocol::create()` construction shortcuts while retaining public constructors.

## 0.1.0 August 24, 2026

- feat: added a framework-agnostic Inertia.js protocol core under the `PHPForge\Inertia` namespace.
- docs: add class-level PHPDoc for the migrated protocol, page, prop, result, and support APIs.
- test: achieve 100% class, method, and line coverage through public behavior with explicit invariant exclusions.
