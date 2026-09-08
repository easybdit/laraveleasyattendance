# Building a UI on top of LaravelEasyAttendance

The package itself is headless — JSON endpoints plus two print-only Blade views (see the main [README](../README.md#exports-bulk-import-notifications--print-views)). This doc shows, in real code, how to build a UI on top of it from four different starting points. Pick whichever matches your app; all four talk to the exact same endpoints, so nothing here is Vue-only or React-only data — it's the same API every time.

## Contents

- [Before you start: auth](#before-you-start-auth)
- [Vue 3](#vue-3)
- [React](#react)
- [Livewire](#livewire)
- [Plain Blade + vanilla JS](#plain-blade--vanilla-js)
- [Which one should I pick?](#which-one-should-i-pick)

## Before you start: auth

Every example below assumes the default setup: the package's routes sit behind `['web', 'auth']` (session-based), same as any other Laravel route — **not** a separate API token. That means:

- The user must already be logged in (via your app's normal Laravel auth) before these calls will succeed — an unauthenticated request gets redirected to your `login` route, not a clean 401.
- Any `POST`/`PUT`/`DELETE` needs Laravel's CSRF token. If your page is rendered by Blade, that's the standard:
  ```html
  <meta name="csrf-token" content="{{ csrf_token() }}">
  ```
  and every fetch/axios example below reads it from there.

If you're calling this from a *separate* SPA (different domain, no shared session) you'd front these routes with [Sanctum](https://laravel.com/docs/sanctum) instead — that's a change to `config('attendance.routes.middleware')`, not to the package.

## Vue 3

A minimal "check in / check out" widget plus today's status, Composition API + `fetch`. No state library needed for something this small.

```vue
<!-- resources/js/components/AttendanceWidget.vue -->
<script setup>
import { ref, onMounted } from 'vue'

const status = ref(null)
const loading = ref(false)

const csrfToken = document.querySelector('meta[name="csrf-token"]').content

async function post(url) {
  loading.value = true
  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
    })
    if (!res.ok) throw new Error(await res.text())
    return res.json()
  } finally {
    loading.value = false
  }
}

async function fetchToday() {
  const res = await fetch('/attendance/today', { headers: { Accept: 'application/json' } })
  status.value = await res.json()
}

const checkIn = async () => { await post('/attendance/check-in'); await fetchToday() }
const checkOut = async () => { await post('/attendance/check-out'); await fetchToday() }

onMounted(fetchToday)
</script>

<template>
  <div>
    <p v-if="status?.first_in">In: {{ status.first_in }}</p>
    <p v-if="status?.last_out">Out: {{ status.last_out }}</p>
    <button :disabled="loading" @click="checkIn">Check in</button>
    <button :disabled="loading" @click="checkOut">Check out</button>
  </div>
</template>
```

A monthly report table, same pattern — fetch on mount, render rows:

```vue
<script setup>
import { ref, onMounted } from 'vue'

const rows = ref([])

onMounted(async () => {
  const res = await fetch('/attendance/reports/employee/42?from=2026-11-01&to=2026-11-30', {
    headers: { Accept: 'application/json' },
  })
  const data = await res.json()
  rows.value = data.rows
})
</script>

<template>
  <table>
    <tr v-for="r in rows" :key="r.date">
      <td>{{ r.date }}</td><td>{{ r.status }}</td><td>{{ r.late_minutes }}m late</td>
    </tr>
  </table>
</template>
```

Or skip building the table yourself entirely — link straight to the CSV: `<a :href="'/attendance/reports/employee/42?from=...&to=...&format=csv'">Download CSV</a>`.

## React

The same widget, hooks instead of `ref`/`onMounted`:

```jsx
// resources/js/components/AttendanceWidget.jsx
import { useState, useEffect, useCallback } from 'react'

const csrfToken = document.querySelector('meta[name="csrf-token"]').content

async function post(url) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
  })
  if (!res.ok) throw new Error(await res.text())
  return res.json()
}

export default function AttendanceWidget() {
  const [status, setStatus] = useState(null)
  const [loading, setLoading] = useState(false)

  const fetchToday = useCallback(async () => {
    const res = await fetch('/attendance/today', { headers: { Accept: 'application/json' } })
    setStatus(await res.json())
  }, [])

  useEffect(() => { fetchToday() }, [fetchToday])

  const handle = async (url) => {
    setLoading(true)
    try { await post(url); await fetchToday() } finally { setLoading(false) }
  }

  return (
    <div>
      {status?.first_in && <p>In: {status.first_in}</p>}
      {status?.last_out && <p>Out: {status.last_out}</p>}
      <button disabled={loading} onClick={() => handle('/attendance/check-in')}>Check in</button>
      <button disabled={loading} onClick={() => handle('/attendance/check-out')}>Check out</button>
    </div>
  )
}
```

A report table:

```jsx
import { useState, useEffect } from 'react'

export default function MonthlyReport({ employeeId, from, to }) {
  const [rows, setRows] = useState([])

  useEffect(() => {
    fetch(`/attendance/reports/employee/${employeeId}?from=${from}&to=${to}`, {
      headers: { Accept: 'application/json' },
    })
      .then((r) => r.json())
      .then((data) => setRows(data.rows))
  }, [employeeId, from, to])

  return (
    <table>
      <tbody>
        {rows.map((r) => (
          <tr key={r.date}><td>{r.date}</td><td>{r.status}</td><td>{r.late_minutes}m late</td></tr>
        ))}
      </tbody>
    </table>
  )
}
```

## Livewire

Livewire is different from the two above in one important way: it's **PHP**, running in the same process as the package — so a Livewire component can skip the HTTP round-trip entirely and call the package's models/traits directly, the same way any of the README's PHP examples do. No `fetch`, no CSRF header wrangling (Livewire handles that itself), no separate JSON contract to keep in sync.

```php
<?php
// app/Livewire/AttendanceWidget.php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class AttendanceWidget extends Component
{
    public ?array $today = null;

    public function mount(): void
    {
        $this->refreshToday();
    }

    public function checkIn(): void
    {
        Auth::user()->checkIn();
        $this->refreshToday();
    }

    public function checkOut(): void
    {
        Auth::user()->checkOut();
        $this->refreshToday();
    }

    private function refreshToday(): void
    {
        $this->today = Auth::user()->attendanceOn(now()->toDateString());
    }

    public function render()
    {
        return view('livewire.attendance-widget');
    }
}
```

```blade
{{-- resources/views/livewire/attendance-widget.blade.php --}}
<div>
    @if ($today['first_in'])
        <p>In: {{ $today['first_in']->format('H:i') }}</p>
    @endif
    @if ($today['last_out'])
        <p>Out: {{ $today['last_out']->format('H:i') }}</p>
    @endif

    <button wire:click="checkIn">Check in</button>
    <button wire:click="checkOut">Check out</button>
</div>
```

A report table works the same way — query `AttendanceSummary` directly instead of calling the JSON endpoint (Livewire 3's `#[Computed]` attribute; use the `getRowsProperty()` naming convention instead if you're still on Livewire 2):

```php
use Livewire\Attributes\Computed;

#[Computed]
public function rows()
{
    return Auth::user()->summaries()
        ->whereBetween('date', [$this->from, $this->to])
        ->orderBy('date')
        ->get();
}
```
```blade
@foreach ($this->rows as $row)
    <tr><td>{{ $row->date->toDateString() }}</td><td>{{ $row->status }}</td></tr>
@endforeach
```

You *can* still hit the HTTP endpoints from Livewire (e.g. via Laravel's `Http` facade) if you'd rather keep one code path shared with a mobile app or another consumer — but calling the model directly is simpler and is the point of using Livewire at all here.

## Plain Blade + vanilla JS

No build step, no framework — just `<script>` and `fetch`, for a single page that doesn't need a full SPA.

```blade
<div id="attendance-widget">
    <p id="status"></p>
    <button id="check-in">Check in</button>
    <button id="check-out">Check out</button>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

async function refreshToday() {
    const res = await fetch('/attendance/today', { headers: { Accept: 'application/json' } });
    const data = await res.json();
    document.getElementById('status').textContent =
        (data.first_in ? `In: ${data.first_in}` : '') + ' ' + (data.last_out ? `Out: ${data.last_out}` : '');
}

async function punch(url) {
    await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' } });
    await refreshToday();
}

document.getElementById('check-in').addEventListener('click', () => punch('/attendance/check-in'));
document.getElementById('check-out').addEventListener('click', () => punch('/attendance/check-out'));
refreshToday();
</script>
```

## Which one should I pick?

| You already have... | Use |
|---|---|
| A Vue-based app (Vite + Vue, or Inertia+Vue) | The Vue example — same fetch calls, drop the component in |
| A React-based app (Vite + React, or Inertia+React) | The React example |
| No SPA framework, mostly Blade | **Livewire** — no npm/build step, calls the package's PHP directly instead of round-tripping through JSON |
| A separate frontend (mobile app, different domain) | The JSON API itself, with [Sanctum](https://laravel.com/docs/sanctum) instead of session auth — same endpoints, different auth layer |

None of these are packages you install — they're starting points to copy into your own app and adjust. If you'd rather not write any UI at all yet, the CSV export/print-view endpoints (see the README) cover the most common "just show me the data" need without any frontend code.
