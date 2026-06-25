<!-- gitnexus:start -->
# GitNexus — Code Intelligence (Integrated Workflow)

This project is indexed by GitNexus as **one-window-bayanihan** (7572 nodes, 15640 edges, 402 clusters, 274 execution flows). GitNexus provides **structural code intelligence** — call graphs, execution flows, blast radius. It complements the agent system (explore/librarian/oracle), not replaces it.

**AGENTS.md has the full coordination workflow.** This file is a quick-reference summary for Claude.

## Pre-Edit Gate (MANDATORY)

```
1. gitnexus_impact({target: "<symbol>", direction: "upstream"})
   → HIGH/CRITICAL? Report user + consult oracle first
2. codegraph_node() or codegraph_explore() to read source
```

## Tool Choice Quick Reference

| Need | Use | Instead of |
|------|-----|-----------|
| "How does X work?" | `codegraph_explore()` + `gitnexus_query()` | grep + Read |
| "What breaks if I change X?" | `gitnexus_impact()` | guessing |
| Read file + see dependents | `codegraph_node({file: "path"})` | Read |
| Read symbol + see callers | `codegraph_node({symbol: "X", includeCode: true})` | Read + grep |
| Pre-commit check | `gitnexus_detect_changes()` | forgetting |
| Safe rename | `gitnexus_rename()` | find-and-replace |
| External docs/API | `librarian` agent (bg) | — |
| Hard reasoning | `oracle` agent | — |

## Parallel Exploration Pattern

```
FIRE IN PARALLEL:
├── codegraph_explore({query: "concept"})     → reads source
├── gitnexus_query({query: "concept"})         → finds flows
└── task(explore, bg)                          → contextual grep

THEN SYNTHESIZE all three results.
```

## Resources

| Resource | Use |
|----------|-----|
| `gitnexus://repo/one-window-bayanihan/context` | Codebase overview |
| `gitnexus://repo/one-window-bayanihan/clusters` | Functional areas |
| `gitnexus://repo/one-window-bayanihan/processes` | Execution flows |
| `gitnexus://repo/one-window-bayanihan/process/{name}` | Trace a flow |

<!-- gitnexus:end -->
