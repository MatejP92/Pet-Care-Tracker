# Contributing

Small, focused improvements are welcome. For a substantial feature or architecture change, discuss the idea with the maintainer first.

## Pull requests

- Start `feat/<description>` branches from the latest `dev` and target pull requests at `dev`. `dev` is the default branch; promotions to `main` come from the repository's `dev` branch.
- All required CI checks must pass before merging into either protected branch.
- Explain what changes and why.
- Describe how you verified the change and any remaining limitations.
- Update setup instructions when your change affects them.
- Use English for code identifiers and shared documentation.

See [README.md](README.md) for local setup and backend test/lint and frontend lint/typecheck/build commands. Run the checks for the applications you change. Include relevant behavioral tests when changing API or database behavior.

## Data and security

Use synthetic data. Do not commit secrets, real environment files, database dumps, private uploads, or real pet records. Do not post credentials or private vulnerability details in public issues or pull requests.

## License

See [LICENSE](LICENSE).
