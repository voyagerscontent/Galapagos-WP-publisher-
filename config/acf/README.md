# ACF mapping profiles

One file per **section**, so each can target its own ACF field group.

- A page template selects a profile with `acf_profile: <name>` (in
  `templates/*.yaml`). That loads `config/acf/<name>.yaml`.
- Templates with **no** `acf_profile` use the default mapping in
  `../acf.yaml` (the generic flat group "Page Content (Flat)").
- If a named profile file is missing, the build falls back to the default
  mapping and emits a warning (it never crashes).

| Profile file | Page types | ACF field group |
| --- | --- | --- |
| `../acf.yaml` (default) | anything without `acf_profile` | Page Content (Flat) |
| `island.yaml` *(planned)* | `destination` | Island Guide Content (`group_island_pages`) |
| `wildlife.yaml` *(planned)* | `wildlife_tier1/2` | _TBD_ |
| `cruise.yaml` *(planned)* | `cruise`, `tour` | _TBD_ |

The field **names** on the right-hand side of each profile must match the ACF
group's field names, and that group must have **Show in REST API = On**.
