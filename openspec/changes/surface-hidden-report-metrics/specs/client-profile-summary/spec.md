## ADDED Requirements

### Requirement: Consolidated client demographic summary card
The Caseload & Clients tab SHALL display a compact client demographic summary card at the top of the tab content, combining gender distribution, client type (OFW/NOK), and vulnerability indicators into a single card.

#### Scenario: Summary card renders with all data
- **WHEN** `genderDistribution`, `clientTypeDistribution`, and `vulnerabilityDistribution` are all loaded
- **THEN** a single card titled "Client Demographics" SHALL render with three stat blocks inline:
  - Total clients count (derived from genderDistribution data sum)
  - Client type split: "X OFW · Y NOK" and sex split: "X Male · Y Female"
  - Vulnerability indicator pills: one per non-zero vulnerability category (PWD, Senior Citizen, Solo Parent, Indigenous Person)

#### Scenario: Card renders with partial data
- **WHEN** only some distributions have data (e.g., gender loaded, vulnerability returns all zeros)
- **THEN** sections with zero data SHALL be omitted from the card rather than showing "0"

#### Scenario: Skeleton while loading
- **WHEN** any of the three data props is still loading
- **THEN** a ChartSkeleton placeholder SHALL render for the entire card

### Requirement: Placement replaces scattered components
The Client Demographics summary card SHALL replace the current layout where the `LazyDemographics` (3-column pie chart row) renders as a standalone section. The lower-detail charts (vulnerabilityDistribution, clientTypeDistribution bar charts) remain in their existing positions.

#### Scenario: Demographics row hidden
- **WHEN** the Client Demographics summary card renders
- **THEN** the old LazyDemographics mini-pie-chart row SHALL NOT render (superseded by the summary)
