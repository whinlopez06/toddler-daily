# Docker Desktop startup on Windows

If startup fails at `dockerInference` with “The file cannot be accessed by the system”, first quit Docker Desktop and reopen it from the Windows Start menu, outside the coding agent's shell.

A matching reported issue describes Windows packaged-app filesystem virtualization interfering with Docker's Windows Unix sockets when Docker is launched by Codex Desktop:
https://github.com/openai/codex/issues/52698

This is a possible cause, not a confirmed diagnosis for every affected computer. Do not rename or remove Docker runtime directories from an agent shell as a first repair attempt. Do not reset Docker or remove its WSL distributions: these actions can destroy unrelated local container data.

If opening from Start still fails, restart Windows and open Docker from Start again. If it persists, use Docker's supported troubleshooting workflow and inspect diagnostics locally before choosing whether to upload them:
https://docs.docker.com/desktop/troubleshoot-and-support/troubleshoot/

The project also supports native PHP, Node.js and PostgreSQL development. Docker is a convenient development environment, not a dependency of the hosted application.
