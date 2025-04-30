# Contributing to Laravel Model Cache

Thank you for considering contributing to Laravel Model Cache! This document outlines the guidelines for contributing to
this project.

## About Laravel Model Cache

Laravel Model Cache is a package that provides efficient caching for Eloquent model queries. It helps improve
application performance by reducing database queries through intelligent caching mechanisms. The package uses Laravel's
built-in cache system with tags to efficiently manage cached queries for Eloquent models.

## Package Architecture

The package consists of the following key components:

1. `HasCachedQueries` trait - Add to your Eloquent models to enable caching
2. `CacheableBuilder` - Extends Eloquent's query builder to add caching functionality
3. `ModelCacheServiceProvider` - Registers the package with Laravel
4. Console commands for cache management

## Code of Conduct

By participating in this project, you agree to abide by
the [Laravel Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Setup

1. Fork the repository
2. Clone your fork: `git clone https://github.com/ymigval/laravel-model-cache.git`
3. Add the upstream repository: `git remote add upstream https://github.com/ymigval/laravel-model-cache.git`
4. Create a branch for your changes: `git checkout -b feature/your-feature-name`
5. Install dependencies: `composer install`

## Development Environment

### Requirements

- PHP ^7.4, ^8.0, ^8.1, ^8.2, or ^8.3
- Laravel 8.x, 9.x, 10.x, 11.x, or 12.x
- Composer

### Installation for Development

After cloning the repository and installing dependencies, you can set up the package for development:

1. Create a Laravel application for testing: `composer create-project laravel/laravel test-app`
2. In your test application's `composer.json`, add a repository pointing to your local clone:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../path/to/your/laravel-model-cache"
    }
  ]
}
```

3. Require the package in development mode: `composer require ymigval/laravel-model-cache:@dev`

## Development Workflow

### Coding Standards

This project follows the [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standard and
the [Laravel coding style](https://laravel.com/docs/contributions#coding-style).

To ensure your code follows these standards, run:

```bash
composer run-script check-style
```

To automatically fix most style issues, run:

``` bash
composer run-script fix-style
```

### Testing

This package has a comprehensive test suite. Before submitting your changes, make sure all tests pass:

``` bash
composer test
```

### Adding New Features

When adding new features, please follow these guidelines:

1. Create a new branch for your feature: `git checkout -b feature/your-feature-name`
2. Update the tests to cover your new feature
3. Update documentation (README.md, PHPDoc comments, etc.)
4. Submit a pull request

### Bug Fixes

When fixing bugs, please follow these guidelines:

1. Create a new branch for your fix: `git checkout -b fix/your-fix-name`
2. Add a test that reproduces the bug
3. Fix the bug
4. Make sure all tests pass
5. Submit a pull request

## Pull Request Process

1. Ensure your code follows the coding standards
2. Update documentation as necessary
3. Add or update tests as necessary
4. The pull request should target the `main` branch
5. Make sure CI tests pass
6. Wait for a maintainer to review your PR

## Release Process

The maintainers will handle the release process. Generally, releases follow these steps:

1. Update version number in composer.json
2. Update CHANGELOG.md with changes since the last release
3. Create a new tag for the release
4. Push the tag to GitHub
5. Create a new release on GitHub

## Support

If you have questions about contributing to Laravel Model Cache, please:

1. Check existing issues to see if your question has already been answered
2. Open a new issue if your question hasn't been addressed
