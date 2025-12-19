# Bootstrap 5 Integration - Guida Rapida

**Versione Bootstrap**: 5.3.2
**Scoping**: Solo pagina plugin (`page=realestate-sync`)
**Docs ufficiali**: https://getbootstrap.com/docs/5.3/

---

## ✅ Bootstrap è Attivo

Bootstrap 5 è caricato **SOLO** sulla dashboard del plugin RealEstate Sync.

**Wrapper scope:**
```html
<div class="wrap realestate-sync-admin bootstrap-scope">
    <!-- Tutto il tuo HTML qui può usare Bootstrap -->
</div>
```

**Nessun conflitto** con WordPress admin styles fuori dal wrapper.

---

## 🎨 Componenti Principali

### 1. Grid System (Layout Responsive)

```html
<!-- Container -->
<div class="container">
    <div class="row">
        <div class="col-md-6">Colonna sinistra (50% su desktop)</div>
        <div class="col-md-6">Colonna destra (50% su desktop)</div>
    </div>
</div>

<!-- 3 colonne uguali -->
<div class="row">
    <div class="col-md-4">33%</div>
    <div class="col-md-4">33%</div>
    <div class="col-md-4">33%</div>
</div>

<!-- Layout complesso -->
<div class="row">
    <div class="col-md-8">Contenuto principale (66%)</div>
    <div class="col-md-4">Sidebar (33%)</div>
</div>
```

**Breakpoints:**
- `col-*` = Sempre
- `col-sm-*` = ≥576px (smartphone)
- `col-md-*` = ≥768px (tablet)
- `col-lg-*` = ≥992px (desktop)
- `col-xl-*` = ≥1200px (large desktop)

### 2. Buttons

```html
<!-- Varianti colore -->
<button class="btn btn-primary">Primary</button>
<button class="btn btn-secondary">Secondary</button>
<button class="btn btn-success">Success</button>
<button class="btn btn-danger">Danger</button>
<button class="btn btn-warning">Warning</button>
<button class="btn btn-info">Info</button>

<!-- Dimensioni -->
<button class="btn btn-lg btn-primary">Large</button>
<button class="btn btn-sm btn-primary">Small</button>

<!-- Outline -->
<button class="btn btn-outline-primary">Outline Primary</button>
```

### 3. Cards

```html
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Titolo Card</h5>
    </div>
    <div class="card-body">
        <p class="card-text">Contenuto della card</p>
        <a href="#" class="btn btn-primary">Azione</a>
    </div>
</div>

<!-- Con immagine -->
<div class="card">
    <img src="..." class="card-img-top" alt="...">
    <div class="card-body">
        <h5 class="card-title">Titolo</h5>
        <p class="card-text">Testo</p>
    </div>
</div>
```

### 4. Alerts

```html
<div class="alert alert-success" role="alert">
    Operazione completata con successo!
</div>

<div class="alert alert-danger" role="alert">
    <strong>Errore!</strong> Qualcosa è andato storto.
</div>

<div class="alert alert-warning alert-dismissible fade show" role="alert">
    Attenzione: controlla i dati.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
```

### 5. Badges

```html
<span class="badge bg-primary">Primary</span>
<span class="badge bg-success">Completato</span>
<span class="badge bg-danger">Errore</span>
<span class="badge bg-warning text-dark">In attesa</span>

<!-- Pill badges -->
<span class="badge rounded-pill bg-info">99+</span>
```

### 6. Forms

```html
<div class="mb-3">
    <label for="email" class="form-label">Email</label>
    <input type="email" class="form-control" id="email" placeholder="name@example.com">
</div>

<div class="mb-3">
    <label for="select" class="form-label">Seleziona opzione</label>
    <select class="form-select" id="select">
        <option selected>Scegli...</option>
        <option value="1">Opzione 1</option>
        <option value="2">Opzione 2</option>
    </select>
</div>

<div class="form-check">
    <input class="form-check-input" type="checkbox" id="check1">
    <label class="form-check-label" for="check1">Checkbox</label>
</div>
```

### 7. Tables

```html
<table class="table table-striped table-hover">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>1</td>
            <td>Item 1</td>
            <td><span class="badge bg-success">Attivo</span></td>
        </tr>
    </tbody>
</table>
```

---

## 🛠️ Utilities (Classi Utility)

### Spacing (Margini e Padding)

```html
<!-- Margin -->
<div class="m-3">Margin 1rem (tutti i lati)</div>
<div class="mt-4">Margin top 1.5rem</div>
<div class="mb-2">Margin bottom 0.5rem</div>
<div class="mx-auto">Margin auto (centrato)</div>

<!-- Padding -->
<div class="p-3">Padding 1rem</div>
<div class="py-4">Padding verticale 1.5rem</div>
<div class="px-2">Padding orizzontale 0.5rem</div>

<!-- Valori: 0, 1, 2, 3, 4, 5 (0 = 0, 5 = 3rem) -->
```

### Text

```html
<p class="text-start">Allineato a sinistra</p>
<p class="text-center">Centrato</p>
<p class="text-end">Allineato a destra</p>

<p class="text-primary">Testo primary</p>
<p class="text-success">Testo success</p>
<p class="text-danger">Testo danger</p>
<p class="text-muted">Testo grigio</p>

<p class="fw-bold">Grassetto</p>
<p class="fst-italic">Italic</p>
<p class="text-decoration-underline">Sottolineato</p>
```

### Display

```html
<div class="d-none">Nascosto</div>
<div class="d-block">Block</div>
<div class="d-flex">Flexbox</div>
<div class="d-grid">Grid</div>

<!-- Responsive -->
<div class="d-none d-md-block">Nascosto mobile, visibile desktop</div>
```

### Flexbox

```html
<div class="d-flex justify-content-between">
    <div>Sinistra</div>
    <div>Destra</div>
</div>

<div class="d-flex align-items-center">
    <div>Centrato verticalmente</div>
</div>

<div class="d-flex flex-column">
    <div>Item 1</div>
    <div>Item 2</div>
</div>
```

### Background & Borders

```html
<div class="bg-primary text-white p-3">Background primary</div>
<div class="bg-success text-white p-3">Background success</div>
<div class="bg-light p-3">Background light</div>

<div class="border">Con bordo</div>
<div class="border border-primary">Bordo primary</div>
<div class="rounded">Bordi arrotondati</div>
<div class="rounded-circle">Cerchio</div>
```

---

## 📝 Esempi Pratici per il Plugin

### Widget Card con Grid

```html
<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Import Immediato</h5>
            </div>
            <div class="card-body">
                <p>Scarica e importa dati XML</p>
                <button class="btn btn-primary">
                    <i class="dashicons dashicons-download"></i> Importa Ora
                </button>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Stato Sistema</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Proprietà:</span>
                    <strong>1,234</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Ultimo import:</span>
                    <span class="badge bg-success">Completato</span>
                </div>
            </div>
        </div>
    </div>
</div>
```

### Tabella Import History

```html
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Storico Import</h5>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Dettagli</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>19/12/2025 10:30</td>
                    <td><span class="badge bg-info">Manuale</span></td>
                    <td><span class="badge bg-success">Completato</span></td>
                    <td>150 nuove, 23 aggiornate</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
```

### Form Configurazione

```html
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Configurazione Import Automatico</h5>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label">Frequenza Import</label>
            <select class="form-select">
                <option>Giornaliero</option>
                <option>Settimanale</option>
                <option>Mensile</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Orario Esecuzione</label>
            <input type="time" class="form-control" value="23:00">
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="testMode">
            <label class="form-check-label" for="testMode">
                Modalità Test
            </label>
        </div>

        <button class="btn btn-primary">Salva Configurazione</button>
    </div>
</div>
```

---

## 🎯 Best Practices

1. **Usa sempre classi Bootstrap invece di CSS custom**
2. **Grid responsive**: Usa `col-md-*` per tablet/desktop
3. **Spacing consistente**: `mb-3`, `p-3` per margini/padding
4. **Cards per widgets**: Ogni widget dovrebbe essere una `.card`
5. **Buttons semantici**: `.btn-primary` per azioni primarie, `.btn-secondary` per secondarie
6. **Alerts per feedback**: Sostituisci gli alert() JavaScript con `.alert-success`

---

## 🔗 Risorse

- **Docs ufficiali**: https://getbootstrap.com/docs/5.3/
- **Examples**: https://getbootstrap.com/docs/5.3/examples/
- **Icons**: Dashicons WordPress + Bootstrap Icons (opzionale)
- **Cheatsheet**: https://bootstrap-cheatsheet.themeselection.com/

---

**Ora puoi modificare i widget e usare tutte le classi Bootstrap!** 🚀
