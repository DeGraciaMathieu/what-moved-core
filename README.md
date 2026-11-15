# what-moved core

The analysis behind [What moved](https://github.com/DeGraciaMathieu/what-moved), the GitHub Action that maps what a pull request moved among your PHP classes and their dependencies. The action installs this repository at a fixed tag and runs its scripts; the action itself holds no code, so that it stays publishable on the GitHub Marketplace, which refuses an action repository with workflows.

- `scripts/graph.php`: lists the classes of a project and the classes each one uses.
- `scripts/report.php`: turns the graphs of the base and the head into the map, the report and the summary of the comment.

## Tests

They run the scripts from the outside, as the action does, on real PHP code, and check what a reviewer is shown: the classes and dependencies found, the summary of the comment, the contexts and arrows of the map. They never look at how the scripts work inside.

- `tests/Analysis`: what the analysis understands of the code, written in each test.
- `tests/PullRequests`: pull requests on a small shop written for the tests, in `tests/Fixtures/shop`.
- `tests/RealProjects`: [laravel.io](https://github.com/laravelio/laravel.io) at fixed commits, cloned into `.cache` on the first run.

```bash
composer install
composer test               # every test
vendor/bin/pest --exclude-group=real   # without laravel.io, offline
```

## Releasing

Tag a version here, then point the `ref` of the core in the `action.yml` of the action to that tag.
