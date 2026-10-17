# Diseño de Subáreas y Repositorio de Plantillas

Este documento describe el diseño técnico de las funcionalidades de **subáreas** y **repositorio de plantillas de certificados**. Es la base para la implementación de la Fase B (base de datos).

---

## 📌 Contexto

### ¿Qué son las subáreas?

Son una **división opcional** dentro de un área. Un área puede funcionar sin subáreas (como hoy) o tener una o varias.


### ¿Son obligatorias?

**No.** Las subáreas son **opcionales**. El sistema sigue funcionando exactamente igual si un área no tiene subáreas.

---

## 👥 Roles y permisos

| Rol | Alcance | Puede |
|---|---|---|
| **Root** | Todo el sistema | Crear áreas, subáreas, usuarios, plantillas base |
| **Admin** | Su área completa | Gestionar todo el área (con o sin subáreas), crear subáreas, asignar gestores |
| **Gestor** | Solo su subárea | Gestionar su subárea (cursos, personas, certificados, diseño) |
| **Persona** | Solo sus certificados | Consultar sus certificados |

### Matriz de permisos

| Acción | Root | Admin | Gestor |
|---|---|---|---|
| Crear áreas | ✅ | ❌ | ❌ |
| Crear subáreas | ✅ | ✅ (solo en su área) | ❌ |
| Crear usuarios | ✅ | ❌ | ❌ |
| Asignar gestores | ✅ | ❌ (solicita al Root) | ❌ |
| Cargar personas (área) | ✅ | ✅ | ❌ |
| Cargar personas (subárea) | ✅ | ✅ | ✅ (solo su subárea) |
| Generar certificados (área) | ✅ | ✅ | ❌ |
| Generar certificados (subárea) | ✅ | ✅ | ✅ (solo su subárea) |
| Editar diseño del área | ✅ | ✅ | ❌ |
| Editar diseño de subárea | ✅ | ✅ | ✅ (solo su subárea) |
| Crear plantillas base | ✅ | ❌ | ❌ |
| Usar plantillas del repositorio | ✅ | ✅ | ✅ |

---

## 🎨 Repositorio de plantillas

### ¿Qué es?

Una **biblioteca central** de plantillas reutilizables. Solo el Root puede crear/editar/eliminar plantillas del repositorio.

### Características de cada plantilla

- **Nombre**: "Certificado A4 Oficial UNJu".
- **Descripción**: para qué sirve.
- **Tamaño de papel**: A4 / A5 / Oficio / Carta / Personalizado.
- **Orientación**: Vertical / Horizontal.
- **Dimensiones**: ancho y alto (para personalizado).
- **Fondo**: imagen.
- **Componentes**: textos, campos dinámicos, QR, firma.
- **Estado**: Activa / Inactiva.

### Cómo se usa

**Escenario 1: Admin usa una plantilla del repositorio**
1. Ana (Admin) entra al editor de diseño de su área.
2. Ve un botón **"Usar plantilla del repositorio"**.
3. Elige "Certificado A4 Oficial UNJu".
4. El sistema **clona** esa plantilla en el diseño de su área.
5. Ana puede **modificarla** (agregar logo, cambiar colores).
6. Guarda el diseño del área.

**Escenario 2: Gestor usa una plantilla del repositorio**
1. Pedro (Gestor) entra al editor de diseño de su subárea.
2. Ve el diseño del área padre precargado.
3. Ve un botón **"Usar plantilla del repositorio"**.
4. Elige "Certificado A5 Maestría".
5. El sistema **clona** esa plantilla en el diseño de su subárea.
6. Pedro puede modificarla.
7. Guarda el diseño de la subárea.

### Regla de inmutabilidad

Las plantillas del repositorio son **inmutables**. Cuando un área las usa, se **clona una copia local** que se puede modificar. Si el Root actualiza la plantilla base, no afecta a las áreas que ya la usaron.

---

## 📐 Tamaños de papel soportados

| Tamaño | Dimensiones | Uso típico |
|---|---|---|
| **A4** | 21 × 29.7 cm | Estándar (impresora común) |
| **A5** | 14.8 × 21 cm | Certificados chicos |
| **Oficio** | 21.6 × 33 cm | Certificados con más contenido |
| **Carta** | 21.6 × 27.9 cm | Estándar EE.UU. |
| **Personalizado** | Definido por el usuario | Diplomas especiales |

### Herencia de tamaño

- **Área sin subárea:** usa el tamaño de la plantilla del área.
- **Subárea con diseño propio:** usa su propio tamaño.
- **Subárea sin diseño propio:** usa el tamaño del área padre.

---

## 🗄️ Modelo de datos

### Nuevas tablas

#### 1. `subareas`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint | PK |
| `area_id` | bigint | FK → areas |
| `nombre` | string | Nombre de la subárea |
| `descripcion` | text (nullable) | Descripción |
| `gestor_id` | bigint (nullable) | FK → users (gestor asignado) |
| `estado` | enum | activa / sin_gestor / inactiva |
| `created_by` | bigint | FK → users |
| `timestamps` | | |

#### 2. `certificate_templates`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint | PK |
| `nombre` | string | Nombre de la plantilla |
| `descripcion` | text (nullable) | Descripción |
| `paper_size` | enum | A4 / A5 / Oficio / Carta / custom |
| `orientation` | enum | portrait / landscape |
| `width_cm` | decimal (nullable) | Para custom |
| `height_cm` | decimal (nullable) | Para custom |
| `background_image` | string (nullable) | Ruta al fondo |
| `components` | json | Componentes del diseño |
| `is_public` | boolean | Si está disponible para todas las áreas |
| `created_by` | bigint | FK → users (Root) |
| `timestamps` | | |

#### 3. `area_templates`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint | PK |
| `area_id` | bigint | FK → areas |
| `subarea_id` | bigint (nullable) | FK → subareas (null = área) |
| `template_id` | bigint | FK → certificate_templates |
| `custom_design` | json (nullable) | Si el área modificó la plantilla |
| `created_by` | bigint | FK → users |
| `timestamps` | | |

### Tablas modificadas

#### `users`
- Agregar: `subarea_id` (bigint, nullable) — para gestores.

#### `courses`
- Agregar: `subarea_id` (bigint, nullable).
- Agregar: `template_id` (bigint, nullable) — qué plantilla usar.

#### `people`
- Agregar: `subarea_id` (bigint, nullable).

#### `certificates`
- Agregar: `subarea_id` (bigint, nullable).
- Agregar: `template_id` (bigint, nullable).

---

## 🔄 Flujos de trabajo

### Flujo 1: Área sin subárea (como hoy)

Root → crea área → asigna Admin → Admin gestiona todo


**Riesgo:** cero. **Los registros actuales siguen funcionando.**

### Flujo 2: Área con subáreas

Root → crea área → asigna Admin
↓
Admin → crea subáreas → solicita gestores al Root
↓
Root → crea usuarios gestores → asigna subáreas
↓
Gestor → gestiona su subárea


### Flujo 3: Diseño de certificados

Área "Posgrado" → diseño A4 (Ana)
├── Subárea "Maestría" → diseño A5 propio (Pedro)
└── Subárea "Doctorado" → hereda diseño A4 del área


**Al generar un certificado:**
- **Certificado del área (sin subárea):** usa el diseño A4 del área.
- **Certificado de Maestría:** usa el diseño A5 de Pedro.
- **Certificado de Doctorado:** usa el diseño A4 del área (heredado).

---

## 🛡️ Estrategia para NO romper nada

### Fase 1: Base de datos (migraciones)
- Crear `subareas`, `certificate_templates`, `area_templates`.
- Agregar columnas `subarea_id` y `template_id` (nullable) a tablas existentes.
- **Probar que la app sigue funcionando.**

### Fase 2: Repositorio de plantillas
- Crear `TemplateController` (CRUD).
- Crear vistas.
- **Solo Root puede usarlo.**

### Fase 3: Subáreas
- Crear `SubareaController` (CRUD).
- Crear vistas.
- **Root/Admin pueden usarlo.**

### Fase 4: Integración
- Modificar formularios de cursos/personas/certificados.
- Modificar controladores (filtros por rol).
- **Probar exhaustivamente.**

### Fase 5: Diseños por subárea
- Modificar el editor de diseño.
- Implementar herencia.
- **Probar.**

---

## ⚠️ Casos borde

### ¿Qué pasa si un área no tiene subáreas?
- Funciona igual que hoy. Los registros tienen `subarea_id = NULL`.

### ¿Qué pasa si una subárea no tiene gestor?
- El Admin del área la gestiona directamente.
- La subárea queda en estado `sin_gestor`.

### ¿Qué pasa si se elimina una subárea?
- Los registros (cursos, personas, certificados) **no se eliminan**.
- Se les pone `subarea_id = NULL` (pasan a pertenecer al área completa).
- **Nada se pierde.**

### ¿Qué pasa si se elimina una plantilla del repositorio?
- Las áreas que la usaban **mantienen su copia local** (`custom_design`).
- Solo se elimina la plantilla base.

### ¿Qué pasa con los datos existentes?
- **No se tocan.**
- Los registros actuales tienen `subarea_id = NULL` y `template_id = NULL`.
- Siguen funcionando como antes.

---

## ✅ Checklist de pruebas (antes de deploy)

### Antes de la implementación
- [ ] Verificar que el sitio funciona (HTTP 200).
- [ ] Login como Root/Admin funciona.
- [ ] Backups al día.

### Después de la Fase 1 (BD)
- [ ] `php artisan migrate` corre sin errores.
- [ ] La app sigue funcionando (HTTP 200).
- [ ] Login funciona.
- [ ] Personas/cursos/certificados existentes siguen accesibles.

### Después de la Fase 2 (plantillas)
- [ ] Root puede crear una plantilla.
- [ ] Root puede ver el listado.
- [ ] Root puede eliminar una plantilla.
- [ ] El resto de la app sigue funcionando.

### Después de la Fase 3 (subáreas)
- [ ] Admin puede crear una subárea.
- [ ] Root puede asignar un gestor.
- [ ] El gestor solo ve su subárea.

### Después de la Fase 4 (integración)
- [ ] Cargar persona con subárea funciona.
- [ ] Generar certificado con subárea funciona.
- [ ] Los registros sin subárea siguen funcionando.

### Después de la Fase 5 (diseños)
- [ ] Gestor puede editar el diseño de su subárea.
- [ ] La herencia funciona (subárea sin diseño usa el del área).
- [ ] Generar PDF con diseño de subárea funciona.

---

## 📅 Cronograma sugerido

| Fase | Duración estimada | Riesgo |
|---|---|---|
| Fase 1 (BD) | 1 día | Bajo |
| Fase 2 (plantillas) | 2 días | Bajo |
| Fase 3 (subáreas) | 2 días | Bajo |
| Fase 4 (integración) | 2 días | Medio |
| Fase 5 (diseños) | 2 días | Medio |
| **Total** | **~9 días hábiles** | |

---

## 🎯 Conclusión

Este diseño permite:
- ✅ Subáreas opcionales (no rompe lo existente).
- ✅ Repositorio central de plantillas.
- ✅ Herencia de diseños.
- ✅ Múltiples tamaños de papel.
- ✅ Roles claros (Root, Admin, Gestor).

**Nada se rompe si se sigue la estrategia por fases.**

---

## 📚 Referencias

- `docs/ARQUITECTURA.md` — Arquitectura general del sistema.
- `docs/DEPLOY.md` — Guía de deploy.
- `docs/SUPERVISOR.md` — Config de workers.
- `docs/BUGS_RESUELTOS.md` — Bugs resueltos.

