<x-layout.app
  title="FROMS - Item Movement History"
  :assets="[
    'resources/css/Main-styles/main.css',
    'resources/css/Main-styles/sidebar.css',
    'resources/css/Warehouse/stock-movements.css',
    'resources/js/Main-js/sidebar.js'
  ]"
>
  <div class="app">
    <x-layout.sidebar department="Warehouse" />

    <main class="main stock-movement-page">
      <x-layout.topbar
        title="Item Movement History"
        subtitle="Audit trail for {{ $inventoryItem->item_name }}"
        notification-count="6"
      />

      <section class="table-card stock-movement-card">
        @include('Warehouse.partials.inventory-movement-history', ['isModal' => false])
      </section>
    </main>
  </div>
</x-layout.app>
