# SIPAE — Sistema de Información de Asistencia y Alimentación Escolar (Colegio OEA)

Sistema web institucional desarrollado para el control diario de asistencia estudiantil, registro de novedades por hora (06:00 a 18:00) y control del servicio de almuerzo escolar (PAE) del Colegio OEA.

---

## 🚀 Arquitectura Tecnológica
- **Lenguaje:** PHP 8.x nativo (con PDO, sesiones seguras y control de acceso por roles).
- **Base de datos:** MySQL / MariaDB (relacional con claves foráneas e integridad referencial).
- **Frontend:** HTML5 semántico y CSS3 institucional responsivo.

---

## 📁 Estructura de Archivos del Proyecto

```text
├── conexion.php                 # Conexión PDO a MySQL y verificación de sesiones
├── login.php                    # Formulario de inicio de sesión institucional
├── dashboard_docente.php        # Registro de asistencia por bloque horario y columna almuerzo
├── procesar_asistencia.php      # Guardado seguro en base de datos (ON DUPLICATE KEY UPDATE)
├── dashboard_coordinador.php    # Panel de coordinación, estadísticas y reporte PAE
├── estudiantes.php              # Directorio y filtros de estudiantes y acudientes
├── usuarios.php                 # Gestión de cuentas (docentes y coordinadores)
├── cambiar_password.php         # Módulo de cambio de contraseña institucional
├── enviar_alerta.php            # Envío de notificaciones y alertas a acudientes
├── logout.php                   # Cierre seguro de sesión
├── sipae_database.sql           # Estructura de base de datos y datos iniciales de prueba
├── img/
│   ├── logo.jpg                 # Escudo institucional Colegio OEA
│   └── marca_agua.png           # Marca de agua institucional
├── .gitignore                   # Exclusión de credenciales y archivos locales
└── README.md                    # Manual y documentación del sistema
```

---

## ⚙️ Puesta en Marcha en XAMPP / Apache

1. **Copiar los archivos** en la carpeta `htdocs/` de XAMPP (ej. `C:/xampp/htdocs/sipae`).
2. **Importar la base de datos:**
   - Iniciar Apache y MySQL en el panel de XAMPP.
   - Acceder a `http://localhost/phpmyadmin`.
   - Crear una base de datos llamada `sipae_db`.
   - Importar el archivo `sipae_database.sql`.
3. **Verificar conexión:**
   - En `conexion.php` las credenciales predeterminadas para XAMPP son:
     - Servidor: `localhost`
     - Base de datos: `sipae_db`
     - Usuario: `root`
     - Contraseña: `""` (vacía)
4. **Acceder a la aplicación:**
   - Abrir en el navegador: `http://localhost/sipae/login.php`.

### Usuarios de prueba:
- **Coordinación:** `coordinacion@colegiooea.edu.co` / Clave: `Admin123*`
- **Docente:** `docente@colegiooea.edu.co` / Clave: `Docente123*`
