// Manejo de modo offline para Sistema Metru

// Registrar Service Worker
/*
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/Metru/sw.js')
      .then(registration => {
        console.log('ServiceWorker registrado:', registration);
      })
      .catch(err => {
        console.log('ServiceWorker falló:', err);
      });
  });
}
  */

// Estado de conexión
let isOnline = navigator.onLine;

// Actualizar UI según estado de conexión
function updateConnectionStatus() {
  const statusElement = document.getElementById('connection-status');
  if (statusElement) {
    if (isOnline) {
      statusElement.innerHTML = '<span class="badge bg-success"><i class="fas fa-wifi"></i> En línea</span>';
      // Intentar sincronizar datos pendientes
      syncPendingData();
    } else {
      statusElement.innerHTML = '<span class="badge bg-danger"><i class="fas fa-wifi-slash"></i> Sin conexión</span>';
    }
  }
}

// Eventos de conexión
window.addEventListener('online', () => {
  isOnline = true;
  updateConnectionStatus();
  showNotification('Conexión restaurada', 'success');
});

window.addEventListener('offline', () => {
  isOnline = false;
  updateConnectionStatus();
  showNotification('Sin conexión - Los datos se guardarán localmente', 'warning');
});

// IndexedDB para almacenamiento offline
class OfflineStorage {
  constructor() {
    this.dbName = 'MetruDB';
    this.version = 1;
    this.db = null;
  }

  async init() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(this.dbName, this.version);

      request.onerror = () => reject(request.error);
      request.onsuccess = () => {
        this.db = request.result;
        resolve(this.db);
      };

      request.onupgradeneeded = (event) => {
        const db = event.target.result;

        // Store para facturas pendientes
        if (!db.objectStoreNames.contains('facturas_pendientes')) {
          const store = db.createObjectStore('facturas_pendientes', { 
            keyPath: 'temp_id', 
            autoIncrement: true 
          });
          store.createIndex('salida_id', 'salida_id', { unique: false });
          store.createIndex('fecha', 'fecha', { unique: false });
        }

        // Store para productos disponibles (cache)
        if (!db.objectStoreNames.contains('productos_cache')) {
          const store = db.createObjectStore('productos_cache', { keyPath: 'id' });
          store.createIndex('salida_id', 'salida_id', { unique: false });
        }

        // Store para clientes (cache)
        if (!db.objectStoreNames.contains('clientes_cache')) {
          db.createObjectStore('clientes_cache', { keyPath: 'id' });
        }
      };
    });
  }

  async saveFacturaOffline(facturaData) {
    await this.init();
    
    const transaction = this.db.transaction(['facturas_pendientes'], 'readwrite');
    const store = transaction.objectStore('facturas_pendientes');
    
    // Agregar timestamp y estado
    facturaData.fecha_guardado = new Date().toISOString();
    facturaData.sincronizado = false;
    
    const request = store.add(facturaData);
    
    return new Promise((resolve, reject) => {
      request.onsuccess = () => {
        showNotification('Factura guardada localmente', 'info');
        resolve(request.result);
      };
      request.onerror = () => reject(request.error);
    });
  }

  async getFacturasPendientes() {
    await this.init();
    
    const transaction = this.db.transaction(['facturas_pendientes'], 'readonly');
    const store = transaction.objectStore('facturas_pendientes');
    const request = store.getAll();
    
    return new Promise((resolve, reject) => {
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  async deleteFactura(tempId) {
    await this.init();
    
    const transaction = this.db.transaction(['facturas_pendientes'], 'readwrite');
    const store = transaction.objectStore('facturas_pendientes');
    
    return store.delete(tempId);
  }

  async cacheProductos(salidaId, productos) {
    await this.init();
    
    const transaction = this.db.transaction(['productos_cache'], 'readwrite');
    const store = transaction.objectStore('productos_cache');
    
    // Limpiar cache anterior
    const index = store.index('salida_id');
    const range = IDBKeyRange.only(salidaId);
    const request = index.openCursor(range);
    
    request.onsuccess = (event) => {
      const cursor = event.target.result;
      if (cursor) {
        store.delete(cursor.primaryKey);
        cursor.continue();
      }
    };
    
    // Guardar nuevos productos
    productos.forEach(producto => {
      producto.salida_id = salidaId;
      store.put(producto);
    });
  }

  async getProductosCache(salidaId) {
    await this.init();
    
    const transaction = this.db.transaction(['productos_cache'], 'readonly');
    const store = transaction.objectStore('productos_cache');
    const index = store.index('salida_id');
    const request = index.getAll(salidaId);
    
    return new Promise((resolve, reject) => {
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }
}

// Instancia global
const offlineStorage = new OfflineStorage();

// Sincronizar datos pendientes
async function syncPendingData() {
  if (!navigator.onLine) return;
  
  try {
    const facturasPendientes = await offlineStorage.getFacturasPendientes();
    
    if (facturasPendientes.length === 0) return;
    
    showNotification(`Sincronizando ${facturasPendientes.length} facturas pendientes...`, 'info');
    
    for (const factura of facturasPendientes) {
      try {
        const response = await fetch('/Metru/includes/sincronizar_factura.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(factura)
        });
        
        if (response.ok) {
          const result = await response.json();
          if (result.success) {
            await offlineStorage.deleteFactura(factura.temp_id);
            console.log('Factura sincronizada:', factura.temp_id);
          }
        }
      } catch (error) {
        console.error('Error sincronizando factura:', error);
      }
    }
    
    showNotification('Sincronización completada', 'success');
    
    // Recargar página para mostrar datos actualizados
    setTimeout(() => {
      window.location.reload();
    }, 1500);
    
  } catch (error) {
    console.error('Error en sincronización:', error);
    showNotification('Error al sincronizar datos', 'danger');
  }
}

// Notificaciones
function showNotification(message, type = 'info') {
  const alertDiv = document.createElement('div');
  alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
  alertDiv.style.zIndex = '9999';
  alertDiv.innerHTML = `
    ${message}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  `;
  
  document.body.appendChild(alertDiv);
  
  setTimeout(() => {
    alertDiv.remove();
  }, 5000);
}

// Inicializar al cargar
document.addEventListener('DOMContentLoaded', () => {
  updateConnectionStatus();
  
  // Si estamos online, intentar sincronizar
  if (navigator.onLine) {
    syncPendingData();
  }
});

// Exportar para uso en otros scripts
window.offlineStorage = offlineStorage;
window.syncPendingData = syncPendingData;
window.showNotification = showNotification;