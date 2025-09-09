<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" {{ $attributes }} role="img" aria-label="ArcherDB target logo">
  <!-- Center = (20,20). Draw outer -> inner so rings stack cleanly -->
  <!-- Colors from your project: white #fefefe, blue #02bce3, red #f03d38, gold #fcec4f, black lines -->
  <!-- Outer white with black outline -->
  <circle cx="20" cy="20" r="19" fill="#fefefe" stroke="#111" stroke-width="1"/>
  <!-- Blue ring -->
  <circle cx="20" cy="20" r="15.5" fill="#02bce3"/>
  <!-- Red outer & inner (two red rings total) -->
  <circle cx="20" cy="20" r="12" fill="#f03d38"/>
  <circle cx="20" cy="20" r="9"  fill="#f03d38"/>
  <!-- Gold (three gold rings total, final two below + X ring) -->
  <circle cx="20" cy="20" r="6.5" fill="#fcec4f"/>
  <circle cx="20" cy="20" r="4.5" fill="#fcec4f"/>
  <!-- X ring (small inner gold with thin black edge) -->
  <circle cx="20" cy="20" r="2.2" fill="#fcec4f" stroke="#111" stroke-width="0.6"/>

  <!-- Thin black separators to match printed targets -->
  <circle cx="20" cy="20" r="15.5" fill="none" stroke="#111" stroke-width="0.6"/>
  <circle cx="20" cy="20" r="12"   fill="none" stroke="#111" stroke-width="0.6"/>
  <circle cx="20" cy="20" r="9"    fill="none" stroke="#111" stroke-width="0.6"/>
  <circle cx="20" cy="20" r="6.5"  fill="none" stroke="#111" stroke-width="0.6"/>
  <circle cx="20" cy="20" r="4.5"  fill="none" stroke="#111" stroke-width="0.6"/>

  <!-- tiny dot in the center (optional, remove if you prefer clean X-ring) -->
  <circle cx="20" cy="20" r="0.6" fill="#111"/>
</svg>

