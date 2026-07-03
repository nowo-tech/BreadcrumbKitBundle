# Engram (MCP)

Repository-local **product spec** and **`REQ-*`** traceability (Makefiles, demos) are described in [Spec-driven development](SPEC-DRIVEN-DEVELOPMENT.md).

This repository includes [`.cursor/mcp.json`](../.cursor/mcp.json) with the **Engram** MCP server for Cursor:

```json
{
  "mcpServers": {
    "engram": {
      "command": "engram",
      "args": ["mcp"]
    }
  }
}
```

Install the `engram` CLI on your machine so Cursor can start the server. Engram is optional for contributing to the bundle; all workflows also work with plain Composer and Docker/`make` commands documented in the root `README.md` and `docs/CONTRIBUTING.md`.
