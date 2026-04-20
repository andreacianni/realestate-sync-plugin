# 2026-04-18 Git Baseline & Rollback Strategy

## Contesto

Il repository del plugin presentava una situazione non allineata tra:

* codice in produzione
* branch `main`
* branch di sviluppo (`hotfix`, feature, ecc.)
* deploy effettuati manualmente via FTP

È stata quindi eseguita una procedura di allineamento per garantire una **baseline affidabile al 100%**.

---

## Stato attuale (VERITÀ DI SISTEMA)

### Branch principali

* `main` → **codice reale in produzione (baseline ufficiale)**
* `hotfix/import-fix` → branch di lavoro allineato alla baseline

### Tag di produzione

* `production-2026-04-18` → **snapshot esatto della produzione**

### Branch di supporto

* `baseline/production-2026-04-18` → riferimento leggibile della baseline
* `archive/main-pre-baseline-2026-04-18` → backup del vecchio `main` (pre-pulizia)

---

## Garanzie ottenute

* Il codice locale è stato verificato contro backup produzione (hash + diff)
* `main` ora rappresenta **una versione reale e funzionante**
* Eliminata dipendenza da stato “ibrido” o deploy parziali
* Possibilità di rollback immediato e sicuro

---

## Strategia di rollback

### Ripristino rapido produzione

```bash
git checkout production-2026-04-18
```

Oppure:

```bash
git checkout baseline/production-2026-04-18
```

---

## Regole operative da ora in avanti

### 1. `main` è sacro

* Deve sempre essere deployabile
* Nessun commit “sporco” o incompleto

### 2. Flusso sviluppo

```text
feature/* o hotfix/* → test → merge su main
```

### 3. Ogni deploy in produzione

```bash
git tag production-YYYY-MM-DD
git push origin production-YYYY-MM-DD
```

### 4. Deploy manuale (FTP)

* Annotare sempre in `docs/`
* Indicare:

  * file modificati
  * motivo
  * data

---

## Anti-pattern da evitare

* ❌ deploy senza tag
* ❌ modifiche manuali non tracciate
* ❌ lavorare direttamente su `main`
* ❌ usare `main` come branch di debug

---

## Note tecniche

* Differenze iniziali rilevate erano dovute a:

  * deploy FTP selettivi
  * line ending (CRLF vs LF)
* Validazione effettuata tramite:

  * hash file sentinella
  * diff normalizzato (`--ignore-cr-at-eol`)

---

## Conclusione

Il repository è ora:

* coerente
* allineato alla produzione
* pronto per evoluzione controllata

👉 Questa è la nuova baseline ufficiale del progetto.
