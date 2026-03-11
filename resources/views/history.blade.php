<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History Logger</title>
    <style>
        body { font-family: sans-serif; margin: 24px; background: #f7f7f7; color: #222; }
        .card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 6px 20px rgba(0,0,0,.08); }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { text-align: left; border-bottom: 1px solid #eee; padding: 10px; vertical-align: top; }
        pre { margin: 0; white-space: pre-wrap; word-break: break-word; }
        .meta { color: #666; margin-top: 8px; }
    </style>
</head>
<body>
<div class="card">
    <h2>History for {{ $modelType }} #{{ $model->getKey() }}</h2>
    <p class="meta">Model class: {{ $model::class }}</p>

    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Event</th>
            <th>Actor</th>
            <th>Snapshot</th>
            <th>Changes</th>
            <th>Logged At</th>
        </tr>
        </thead>
        <tbody>
        @forelse($histories as $history)
            <tr>
                <td>{{ $history->id }}</td>
                <td>{{ $history->event }}</td>
                <td>{{ $history->actor_type && $history->actor_id ? $history->actor_type . ' #' . $history->actor_id : 'system' }}</td>
                <td><pre>{{ json_encode($history->snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></td>
                <td><pre>{{ json_encode($history->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></td>
                <td>{{ $history->created_at }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6">No history entries found.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <div style="margin-top: 16px;">
        {{ $histories->links() }}
    </div>
</div>
</body>
</html>
