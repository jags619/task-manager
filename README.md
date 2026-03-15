# ✦ Taskboard

A real-time Kanban task management app built with **Laravel 11**, **Vue 3**, and **Inertia.js**.
Previously a simple Blade-based task manager — now a full real-time collaborative board.

---

## Features

### Authentication
- Secure register, login and logout via Laravel Breeze
- Each user only sees their own tasks, projects and tags
- Activity feed logs login, logout and registration events

### Kanban Board
- Drag and drop tasks between projects
- Drag to reorder tasks within a project (priority auto-updates)
- Completed tasks automatically sink to the bottom
- Inline task name editing with Escape to cancel

### Task Management
- Create, edit, delete tasks
- Mark tasks as complete / uncomplete
- Set due dates with overdue highlighting
- Assign color-coded tags to tasks

### Project Management
- Create and delete projects
- Progress bars showing completed vs total tasks per project

### Tags
- Create custom tags with color picker
- Filter tasks by tag
- Tag management with delete

### Real-time Broadcasting
- Live activity feed via Laravel Reverb (WebSockets)
- Private channel broadcasts on task drag between projects
- All actions broadcast instantly — no page refresh needed

### Activity Feed
- Logs every action with icon and relative timestamp
- Covers: login, logout, register, project create/delete, task create/move/rename/complete/delete/reorder/tag update/due date change

### Search & Filters
- Live search across task names, project names, tags and due dates
- Filter by project, tag, completion status — all stackable

### UI
- Elegant dark / light theme toggle with smooth transitions
- Custom CSS variable design system
- DM Sans + DM Mono typography
- Thin scrollbars, refined cards, gradient progress bars

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 11, PHP 8.3+ |
| Frontend | Vue 3 + Inertia.js |
| Authentication | Laravel Breeze |
| Realtime | Laravel Reverb (WebSockets) |
| Styling | Tailwind CSS + Custom CSS Variables |
| Drag & Drop | SortableJS |
| Database | MySQL |
| Build Tool | Vite |

---

## Setup Instructions (Windows 11)

### Prerequisites
- PHP 8.3+
- Composer
- MySQL
- Node.js

### 1. Clone & Install
```bash
git clone <repo-url>
cd taskboard
composer install
npm install
copy .env.example .env
php artisan key:generate
```

### 2. Configure `.env`
Update your database credentials and add Reverb config:
```env
DB_CONNECTION=mysql
DB_DATABASE=taskboard
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_CONNECTION=reverb

REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### 3. Migrate & Run
```bash
php artisan migrate
```

Start all servers:
```bash
# Terminal 1 — Laravel
php artisan serve

# Terminal 2 — Vite
npm run dev

# Terminal 3 — Reverb WebSocket server
php artisan reverb:start

# Terminal 4 — Queue worker
php artisan queue:listen
```

Open in browser: `http://127.0.0.1:8000`

Register a new account to get started — all data is scoped per user.

---

## Project Structure
```
app/
├── Events/
│   ├── ActivityCreated.php         # broadcasts to public activities channel
│   └── TaskMoved.php               # broadcasts to private user channel
├── Listeners/
│   └── LogUserLogin.php            # logs login event
├── Observers/
│   ├── TaskObserver.php            # fires events on task create/update/delete/reorder
│   ├── ProjectObserver.php         # fires events on project create/delete
│   └── UserObserver.php            # fires events on user register
├── Models/
│   ├── Task.php
│   ├── Project.php
│   ├── Activity.php
│   ├── Tag.php
│   └── User.php
└── Http/Controllers/
    ├── TaskController.php
    ├── ProjectController.php
    └── TagController.php

resources/js/Pages/Tasks/
└── Index.vue                       # main Kanban board

routes/
├── web.php                         # app routes
└── channels.php                    # broadcast channel auth
```

---

## Broadcasting Channels

| Channel | Type | Event | Trigger |
|---|---|---|---|
| `activities` | Public | `ActivityCreated` | all task, project, user actions |
| `user.{id}` | Private | `TaskMoved` | drag between projects or reorder |

---

## Activity Feed Events

| Action | Trigger |
|---|---|
| `user_registered` | New user registers |
| `user_loggedin` | User logs in |
| `user_loggedout` | User logs out |
| `project_created` | New project created |
| `project_deleted` | Project deleted |
| `task_created` | New task added |
| `task_moved` | Task dragged to different project |
| `task_reordered` | Task priority changed via drag |
| `task_renamed` | Task name edited inline |
| `task_completed` | Task marked complete |
| `task_uncompleted` | Task completion revoked |
| `task_deleted` | Task deleted |
| `task_tags_updated` | Tags added or removed from task |
| `due_date_changed` | Task due date updated |

---

## License
MIT