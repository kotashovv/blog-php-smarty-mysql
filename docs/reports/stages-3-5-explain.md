# EXPLAIN: этапы 3–5

Снято на MySQL 8.4, 22.10.2026. Рабочий набор: 40 статей, 6 категорий, 65 связей. Временное расширение на 10 000 статей выполнено внутри откатываемой транзакции; данные удалены откатом. Идентификаторы относятся к этому локальному запуску.

## Основной набор

### home

```sql
SELECT ranked.* FROM (
                SELECT p.*, pc.category_id,
                       ROW_NUMBER() OVER (
                           PARTITION BY pc.category_id ORDER BY p.published_at DESC, p.id DESC
                       ) AS position_in_category
                FROM posts p JOIN post_category pc ON pc.post_id = p.id
             ) ranked WHERE position_in_category <= 3
             ORDER BY category_id, position_in_category
```

Параметры: `[]`.

| select_type | table | type | key | rows | Extra |
|---|---|---|---|---:|---|
| PRIMARY | <derived2> | ALL | — | 85 | Using where; Using filesort |
| DERIVED | pc | index | PRIMARY | 85 | Using index; Using temporary; Using filesort |
| DERIVED | p | eq_ref | PRIMARY | 1 | — |

### categories_for_posts

```sql
SELECT c.*, pc.post_id FROM categories c
             JOIN post_category pc ON pc.category_id = c.id
             WHERE pc.post_id IN (?, ?, ?, ?, ?, ?, ?, ?, ?) ORDER BY c.id
```

Параметры: `{"1": [678, 1], "2": [677, 1], "3": [680, 1], "4": [682, 1], "5": [686, 1], "6": [679, 1], "7": [684, 1], "8": [688, 1], "9": [716, 1]}`.

| select_type | table | type | key | rows | Extra |
|---|---|---|---|---:|---|
| SIMPLE | pc | range | PRIMARY | 19 | Using where; Using index; Using temporary; Using filesort |
| SIMPLE | c | eq_ref | PRIMARY | 1 | — |

### category_views

```sql
SELECT p.* FROM posts p JOIN post_category pc ON pc.post_id = p.id
             WHERE pc.category_id = :category_id
             ORDER BY p.views DESC, p.id DESC LIMIT :limit OFFSET :offset
```

Параметры: `{"category_id": [133, 1], "limit": [9, 1], "offset": [0, 1]}`.

| select_type | table | type | key | rows | Extra |
|---|---|---|---|---:|---|
| SIMPLE | p | ALL | — | 40 | Using filesort |
| SIMPLE | pc | eq_ref | PRIMARY | 1 | Using index |

### related

```sql
SELECT p.*, COUNT(shared.category_id) AS common_categories
             FROM posts p
             LEFT JOIN post_category candidate ON candidate.post_id = p.id
             LEFT JOIN post_category shared
               ON shared.category_id = candidate.category_id AND shared.post_id = :source_id
             WHERE p.id <> :excluded_id
             GROUP BY p.id
             ORDER BY common_categories DESC, p.published_at DESC, p.id DESC
             LIMIT 3
```

Параметры: `{"source_id": [716, 1], "excluded_id": [716, 1]}`.

| select_type | table | type | key | rows | Extra |
|---|---|---|---|---:|---|
| SIMPLE | p | range | PRIMARY | 40 | Using where; Using temporary; Using filesort |
| SIMPLE | candidate | ref | PRIMARY | 2 | Using index |
| SIMPLE | shared | eq_ref | PRIMARY | 1 | Using index |

### category_date

```sql
SELECT p.* FROM posts p JOIN post_category pc ON pc.post_id = p.id
             WHERE pc.category_id = :category_id
             ORDER BY p.published_at DESC, p.id DESC LIMIT :limit OFFSET :offset
```

Параметры: `{"category_id": [133, 1], "limit": [9, 1], "offset": [0, 1]}`.

| select_type | table | type | key | rows | Extra |
|---|---|---|---|---:|---|
| SIMPLE | p | ALL | — | 40 | Using filesort |
| SIMPLE | pc | eq_ref | PRIMARY | 1 | Using index |

## Дополнительные 10 000 статей

### date

```sql
SELECT p.* FROM posts p JOIN post_category pc ON pc.post_id = p.id
             WHERE pc.category_id = :category_id
             ORDER BY p.published_at DESC, p.id DESC LIMIT :limit OFFSET :offset
```

Параметры: `{"category_id": [133, 1], "limit": [9, 1], "offset": [0, 1]}`.

| select_type | table | type | key | rows | Extra |
|---|---|---|---|---:|---|
| SIMPLE | pc | ref | post_category_category_id | 2025 | Using index; Using temporary; Using filesort |
| SIMPLE | p | eq_ref | PRIMARY | 1 | — |

### views

```sql
SELECT p.* FROM posts p JOIN post_category pc ON pc.post_id = p.id
             WHERE pc.category_id = :category_id
             ORDER BY p.views DESC, p.id DESC LIMIT :limit OFFSET :offset
```

Параметры: `{"category_id": [133, 1], "limit": [9, 1], "offset": [0, 1]}`.

| select_type | table | type | key | rows | Extra |
|---|---|---|---|---:|---|
| SIMPLE | pc | ref | post_category_category_id | 2025 | Using index; Using temporary; Using filesort |
| SIMPLE | p | eq_ref | PRIMARY | 1 | — |

## Интерпретация

На 10 040 статьях обе сортировки категории используют `post_category_category_id` (`ref`, оценка 2025 связей), затем PRIMARY posts (`eq_ref`, 1 строка на связь). Полного сканирования таблиц нет. Filesort и временный набор остаются: индекс связи отбирает категорию, затем её статьи нужно упорядочить по данным posts. На исходных 40 статьях оптимизатор может выбрать ALL по posts; это решение для маленькой таблицы, а не отсутствие индексов.

ROW_NUMBER требует упорядочить связи внутри категорий и обработать все подходящие связи. Запрос похожих рассматривает кандидатов, считает совпадения и сортирует итоговый набор; это простой точный алгоритм для небольшого блога, не обещание постоянной стоимости на большой базе. Категории карточек читаются пакетно через IN, а не отдельным запросом для каждого поста.

Источник: `tools/check-blog.php --exercise-large`; полные параметры и планы локального запуска — `var/blog-checks.json`. Для справки: [оконные функции MySQL](https://dev.mysql.com/doc/refman/8.4/en/window-functions-usage.html), [правила PDO placeholders](https://www.php.net/manual/en/pdo.prepare.php).
