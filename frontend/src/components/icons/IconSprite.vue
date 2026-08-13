<script setup lang="ts">
// Stroke-based icon sprite (stroke-width 2) mounted once in App.vue.
// Individual icons render via <Icon name="..." />, which references these symbols by #i-<name>.
</script>

<template>
  <svg style="display: none" aria-hidden="true">
    <defs>
      <symbol id="i-home" viewBox="0 0 24 24"><path d="M4 11.5 12 4l8 7.5" /><path d="M6 10v9a1 1 0 0 0 1 1h3v-6h4v6h3a1 1 0 0 0 1-1v-9" /></symbol>
      <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></symbol>
      <symbol id="i-minus" viewBox="0 0 24 24"><path d="M5 12h14" /></symbol>
      <symbol id="i-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></symbol>
      <symbol id="i-crosshair" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" /><path d="M12 2v4M12 18v4M2 12h4M18 12h4" /><circle cx="12" cy="12" r="7.5" /></symbol>
      <symbol id="i-map" viewBox="0 0 24 24"><path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2Z" /><path d="M9 4v14M15 6v14" /></symbol>
      <symbol id="i-business" viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2" /><path d="M9 8h2M13 8h2M9 12h2M13 12h2M9 16h2M13 16h2" /><path d="M2 21h20" /></symbol>
      <symbol id="i-plus-square" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="5" /><path d="M12 8v8M8 12h8" /></symbol>
      <symbol id="i-clipboard" viewBox="0 0 24 24"><path d="M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1Z" /><rect x="6" y="6" width="12" height="15" rx="2" /><path d="M9 11h6M9 15h6" /></symbol>
      <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5" /><path d="M5 20c1.5-4 5-5.5 7-5.5s5.5 1.5 7 5.5" /></symbol>
      <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3l7 3v6c0 5-3.2 7.7-7 9-3.8-1.3-7-4-7-9V6l7-3Z" /><path d="M9 12l2 2 4-4" /></symbol>
      <symbol id="i-star" viewBox="0 0 24 24"><path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.9L12 3.5Z" /></symbol>
      <symbol id="i-chat" viewBox="0 0 24 24"><path d="M4 5h16a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H9l-4 4v-4H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" /></symbol>
      <symbol id="i-wallet" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="13" rx="2" /><path d="M3 10h18" /><circle cx="16.5" cy="14" r="1.1" fill="currentColor" stroke="none" /></symbol>
      <symbol id="i-pin" viewBox="0 0 24 24"><path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11Z" /><circle cx="12" cy="10" r="2.5" /></symbol>
      <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5" /><path d="M12 7.5V12l3 2" /></symbol>
      <symbol id="i-send" viewBox="0 0 24 24"><path d="M4 11l16-7-6 16-3-6-7-3Z" /></symbol>
      <symbol id="i-check-circle" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5" /><path d="M8.5 12.3l2.3 2.3 4.7-5" /></symbol>
      <symbol id="i-check" viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7" /></symbol>
      <symbol id="i-chevron-right" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7" /></symbol>
      <symbol id="i-chevron-down" viewBox="0 0 24 24"><path d="M5 9l7 7 7-7" /></symbol>
      <symbol id="i-arrow-left" viewBox="0 0 24 24"><path d="M19 12H5M11 6l-6 6 6 6" /></symbol>
      <symbol id="i-search" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5" /><path d="M20 20l-5-5" /></symbol>
      <symbol id="i-users" viewBox="0 0 24 24"><circle cx="8.5" cy="8" r="3" /><path d="M2.5 19c1-3.5 3.7-5 6-5s5 1.5 6 5" /><circle cx="17" cy="9" r="2.5" /><path d="M15.5 13.2c2 0 4.3 1.2 5 4.8" /></symbol>
      <symbol id="i-receipt" viewBox="0 0 24 24"><path d="M6 3h12v18l-2.5-1.5L13 21l-2.5-1.5L8 21l-2-1.5V3Z" /><path d="M8.5 8h7M8.5 12h7" /></symbol>
      <symbol id="i-grid" viewBox="0 0 24 24"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5" /><rect x="13.5" y="3.5" width="7" height="7" rx="1.5" /><rect x="3.5" y="13.5" width="7" height="7" rx="1.5" /><rect x="13.5" y="13.5" width="7" height="7" rx="1.5" /></symbol>
      <symbol id="i-alert" viewBox="0 0 24 24"><path d="M12 4 21.5 20h-19L12 4Z" /><path d="M12 10v4" /><circle cx="12" cy="17" r="0.7" fill="currentColor" stroke="none" /></symbol>
      <symbol id="i-gauge" viewBox="0 0 24 24"><path d="M4 15a8 8 0 1 1 16 0" /><path d="M12 15l4-5" /><circle cx="12" cy="15" r="1" fill="currentColor" stroke="none" /></symbol>
      <symbol id="i-logout" viewBox="0 0 24 24"><path d="M9 4H6a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h3" /><path d="M14 8l4 4-4 4M18 12H9" /></symbol>
      <symbol id="i-help" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5" /><path d="M9.5 9a2.5 2.5 0 1 1 3.6 2.2c-.7.4-1.1.9-1.1 1.8" /><circle cx="12" cy="16.5" r="0.6" fill="currentColor" stroke="none" /></symbol>
      <symbol id="i-bell" viewBox="0 0 24 24"><path d="M6 10a6 6 0 1 1 12 0c0 4 1.5 5.5 1.5 5.5H4.5S6 14 6 10Z" /><path d="M10 19a2 2 0 0 0 4 0" /></symbol>
      <symbol id="i-car" viewBox="0 0 24 24"><path d="M4 16V12l2-4h12l2 4v4" /><rect x="3" y="16" width="18" height="3" rx="1" /><circle cx="7.5" cy="19" r="1.3" /><circle cx="16.5" cy="19" r="1.3" /></symbol>
      <symbol id="i-package" viewBox="0 0 24 24"><path d="M12 3l8 4v10l-8 4-8-4V7l8-4Z" /><path d="M4 7l8 4 8-4M12 11v10" /></symbol>
      <symbol id="i-cart" viewBox="0 0 24 24"><circle cx="9" cy="20" r="1.3" /><circle cx="17" cy="20" r="1.3" /><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h7.6a2 2 0 0 0 2-1.6L21 8H6" /></symbol>
      <symbol id="i-sparkle" viewBox="0 0 24 24"><path d="M12 3v4M12 17v4M4 12h4M16 12h4M6.5 6.5l2.5 2.5M15 15l2.5 2.5M17.5 6.5 15 9M9 15l-2.5 2.5" /></symbol>
      <symbol id="i-truck" viewBox="0 0 24 24"><rect x="2" y="8" width="11" height="8" rx="1.5" /><path d="M13 11h4l3 3v2h-7Z" /><circle cx="6" cy="18" r="1.4" /><circle cx="17" cy="18" r="1.4" /></symbol>
      <symbol id="i-wrench" viewBox="0 0 24 24"><path d="M14.5 5.5a4 4 0 0 1-5.6 4.9L5 14.3l1.7 1.7 3.9-3.9a4 4 0 0 0 4.9-5.6Z" /></symbol>
      <symbol id="i-layers" viewBox="0 0 24 24"><path d="M12 3 3 8l9 5 9-5-9-5Z" /><path d="M3 12l9 5 9-5M3 16l9 5 9-5" /></symbol>
      <symbol id="i-image" viewBox="0 0 24 24"><rect x="3.5" y="3.5" width="17" height="17" rx="3" /><circle cx="9" cy="9" r="1.6" /><path d="M4 17l5-5 3.5 3.5L17 11l3 3" /></symbol>
      <symbol id="i-card" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2.5" /><path d="M3 10h18" /><path d="M7 14.5h4" /></symbol>
      <symbol id="i-bookmark" viewBox="0 0 24 24"><path d="M6 3.5h12a1 1 0 0 1 1 1V21l-7-4-7 4V4.5a1 1 0 0 1 1-1Z" /></symbol>
      <symbol id="i-motorcycle" viewBox="0 0 24 24"><circle cx="5.5" cy="17.5" r="2.8" /><circle cx="18.5" cy="17.5" r="2.8" /><path d="M8.2 17.5h5l3-6h3M12 11.5H9.5L8 8.5H5.5" /><path d="M13.2 11.5 15 15" /></symbol>
      <symbol id="i-van" viewBox="0 0 24 24"><rect x="2" y="8" width="20" height="9" rx="2" /><path d="M2 12h20M8 8v9" /><circle cx="7" cy="19.5" r="1.4" /><circle cx="17" cy="19.5" r="1.4" /></symbol>
      <symbol id="i-camera" viewBox="0 0 24 24"><path d="M4 8h3l1.5-2.5h7L17 8h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Z" /><circle cx="12" cy="13" r="3.5" /></symbol>
      <symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="16" rx="2.5" /><path d="M3.5 10h17M8 3v4M16 3v4" /></symbol>
      <symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5" /><path d="M12 11v6" /><circle cx="12" cy="7.7" r="0.6" fill="currentColor" stroke="none" /></symbol>
      <symbol id="i-edit" viewBox="0 0 24 24"><path d="M15.5 4.5l4 4L8 20H4v-4L15.5 4.5Z" /><path d="M13 7l4 4" /></symbol>
      <symbol id="i-arrow-right" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6" /></symbol>
      <symbol id="i-trash" viewBox="0 0 24 24"><path d="M3 6h18" /><path d="M8 3h8a1 1 0 0 1 1 1v1H7V4a1 1 0 0 1 1-1Z" /><path d="M5 8h14v13a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V8Z" /></symbol>
    </defs>
  </svg>
</template>
