## ADDED Requirements

### Requirement: Provincial geographic map
The Caseload & Clients tab SHALL display an interactive Philippine province map showing case distribution by province, as an alternative to the existing horizontal bar chart.

#### Scenario: Map renders with province data
- **WHEN** `geographicDistribution` data is loaded and contains province names with case counts
- **THEN** an inline SVG map of the Philippines (Region VII focus) SHALL render, with each province filled by a color scale from the COLORS.chartPalette based on case density

#### Scenario: Province hover shows tooltip
- **WHEN** the user hovers over a province SVG path
- **THEN** a tooltip SHALL appear showing the province name and case count (e.g., "Cebu — 342 cases")

#### Scenario: Click province filters view
- **WHEN** the user clicks a province on the map
- **THEN** the click SHALL update the province filter parameter (`setProvince(provinceId)`), re-filtering all report data to that province

#### Scenario: Empty state
- **WHEN** `geographicDistribution` is null or empty
- **THEN** a "No geographic data available." message SHALL display in place of the map

#### Scenario: Loading state
- **WHEN** `geographicDistribution` data is still loading
- **THEN** a ChartSkeleton placeholder SHALL render

### Requirement: Map/Bar view toggle
The geographic section SHALL provide a map/bar toggle control so users can switch between the SVG map view and the existing horizontal bar chart.

#### Scenario: Default view is map
- **WHEN** the geographic section first renders and map data is available
- **THEN** the default visible view SHALL be the map, with a "Bar chart" toggle option visible

#### Scenario: Toggle switches view
- **WHEN** the user clicks the toggle to switch from map to bar chart
- **THEN** the existing horizontal bar chart SHALL render in place of the map, using the same `geographicDistribution` data

### Requirement: Region VII province coverage
The SVG map SHALL cover the Central Visayas region (Region VII) provinces: Cebu, Bohol, Negros Oriental, Siquijor, plus optionally nearby areas with case data.

#### Scenario: Province outside Region VII has data
- **WHEN** `geographicDistribution` includes provinces outside Region VII (e.g., Manila, Davao)
- **THEN** those provinces SHALL still render in the bar chart view but SHALL NOT appear on the SVG map (the bar chart is the comprehensive view)

## MODIFIED Requirements

*(No existing specs to modify.)*
