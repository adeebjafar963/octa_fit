# Coach knowledge corpus

Phase 11F stores authoritative knowledge in the Laravel database:

- `knowledge_sources`
- `knowledge_documents`
- `knowledge_chunks`

Seed with:

```bash
php artisan db:seed --class=KnowledgeSeeder
```

This folder is reserved for optional future file-based imports. It is **not** a second RAG store. Do not add fabricated papers here.
