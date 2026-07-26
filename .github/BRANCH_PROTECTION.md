# Main branch protection

Use a GitHub repository ruleset targeting the `main` branch with:

- deletion and force pushes blocked;
- pull requests required;
- branches required to be up to date before merging;
- `quality-gate` required as the stable status check;
- stale approvals dismissed after new commits;
- review conversations resolved before merging.

The safe initial ruleset requires pull requests and `quality-gate`, but keeps
required approvals at zero while the owner and automation share one GitHub
identity.

## Identity requirement

An agent authenticated as `@DiasMazhenov` is the same GitHub actor as the
repository owner. GitHub cannot distinguish the human from that agent, and a
pull-request author cannot approve their own pull request.

For technically enforced human approval, give OpenCode a separate bot GitHub
account, keep `@DiasMazhenov` as the `CODEOWNERS` owner, and then enable:

- one required approval;
- required code-owner review;
- approval of the most recent push;
- no bypass permission for the bot account.

Until a separate bot identity exists, keep required approvals at zero to avoid
locking the owner out. The PR and green `quality-gate` remain technically
required; review the diff manually before merging.
