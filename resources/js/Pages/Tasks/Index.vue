<script setup>
import { onMounted, ref, onBeforeUnmount, computed, nextTick, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Sortable from 'sortablejs';
import { toFormData } from 'axios';
import Echo from 'laravel-echo';
//import { debounce } from 'lodash'


//props sent from TaskCrontroller

const props = defineProps({
    tasks: Array,
    projects: Array,
    projectId: [Number, String, null],
    tags: Array,
    activities: Array
});

//local refs for forms
const darkMode = ref(localStorage.getItem('theme') === 'dark')

const taskForm = ref('');
const selectedTags = ref([]);
const showTagDropdown = ref(false);
const newProject = ref('');
const newTask = ref('');
const selectedProject = ref('all');
const selectedProjectForTask = ref('');
const taskList = ref(null);
const newDueDate = ref('');
const newTag = ref('');
const newTagColor = ref('#3b82f6');

const deletingTag = ref(null)
const showTagModal = ref(false);
const openTagTaskId = ref(null);
const tagDropdown = ref(null);
const selectedTaskTags = ref('all');

const editingTaskId = ref(null)
const taskEditInput = ref({})

const searchQuery = ref('')

const showTagFilterDropdown = ref(null)
const hoveredTag = ref(null)
const tagFilterDropdownEl = ref(null)



//const activities = ref(props.activities ?? [])
const userId = usePage().props.auth.user.id

const selectedCompletion = ref('all')  // 'all' | 'completed' | 'pending'

watch(darkMode,(value)=>{
localStorage.setItem('theme', value ? 'dark':'light')

})


//console.log (props.projects);
onMounted(() => {

    //activity broadcast

    window.Echo.channel('activities')
        .listen('ActivityCreated',(e)=>{
            console.log('activities', e);
            activities.value.unshift(e.activity)
        })

    window.Echo.private(`user.${userId}`)
        .listen('TaskMoved', (e) => {
            if (e.activity.action === 'task_moved') {
                moveTaskInBoard(e.activity.meta.taskId, e.activity.meta.from, e.activity.meta.to)
            }

            // ✅ ADD THIS
            if (e.activity.action === 'task_reordered') {
                // activity feed already handled by ActivityCreated listener
                // nothing extra needed on the board UI for reorder
                console.log('Task reordered in', e.activity.meta.project_name)
            }
        })

    //for dropdown outside click
    document.addEventListener('click', handleClickOutside)

  

    //for tasklist priority drag and drop
    //if (!taskList.value) return
document.querySelectorAll(".taskList").forEach(el => {
    //console.log(el);
    Sortable.create(el, {
        group:{
            name: 'tasks',
            pull: true,
            put: true   
        },
        animation: 150,
        ghostClass: 'dragging',
        onEnd: (evt) => {
            const taskId = evt.item.dataset.id
            const oldProjectId = evt.from.dataset.project
            const newProjectId = evt.to.dataset.project
            const order = []
            //console.log(evt);
            evt.to.querySelectorAll('li').forEach(li => {
                order.push(li.dataset.id)
            })
            
            if(oldProjectId !== newProjectId){
                router.put(`/tasks/${taskId}`,{
                    project_id : newProjectId
                },{
                    preserveScroll:true
                })
            }
            else{
                router.post('/tasks/reorder', {
                    //task_id: taskId,
                    order: order, 
                    project_id: newProjectId
                },{
                    preserveScroll:true
                })
            }
        }
    })
})
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside)
})

function moveTaskInBoard(taskId, fromProjectName, toProjectName) {
    // Find the source and destination projects
    const fromProject = props.projects.find(p => p.name === fromProjectName)
    const toProject = props.projects.find(p => p.name === toProjectName)

    if (!fromProject || !toProject) return

    // Find the task in the source project
    const taskIndex = fromProject.tasks.findIndex(t => t.id == taskId)

    if (taskIndex === -1) return

    // Remove task from source project
    const [task] = fromProject.tasks.splice(taskIndex, 1)

    // Add task to destination project
    toProject.tasks.push(task)
}

function handleClickOutside(event){
    //console.log(event);
   
    //if(!tagDropdown.value) return
    //else(showTagDropdown.value = false)
    if (tagDropdown.value && !tagDropdown.value.contains(event.target)) {
        openTagTaskId.value = false
    }

      // tag filter dropdown
    if (tagFilterDropdownEl.value && !tagFilterDropdownEl.value.contains(event.target)) {
        showTagFilterDropdown.value = false
    }
}

function createProject(){
    router.post('/projects', { name: newProject.value}, {
        onSuccess: () => newProject.value = ''
    })
}

function createTag(){
    router.post('/tags', {
        name: newTag.value,
        color: newTagColor.value
    }, {
        onSuccess: ()=>{
            newTag.value = '',
            newTagColor.value = '#3b82f6',
            showTagModal.value = false
        } 
    })
}

function openTagModal(){
    showTagDropdown.value = false
    showTagModal.value = true
}

function deleteTag(tagId){
    //if(!confirm("Delete this tag?")) return;
    if (!confirm("Delete this tag?")) return;
    router.delete(`/tags/${tagId}`, {
         preserveScroll: true,
         preserveState: true,
        onSuccess: () => {
            deletingTag.value = null
        }
    })
}

function createTask(){
    if(!taskForm.value.checkValidity()){
        taskForm.value.reportValidity()
        return
    }

    router.post('/tasks', { 
        name: newTask.value, 
        project_id:selectedProjectForTask.value,
        due_date: newDueDate.value || null,
        tags: selectedTags.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            newTask.value = ''
            selectedProjectForTask.value= ''
            newDueDate.value = ''
            selectedTags.value = []
            showTagDropdown.value = false 

            nextTick(() => {
                taskNameInput.value.focus()
            })
        }
    })
}

function updateTask(task){
    router.put(`/tasks/${task.id}`, { 
        name: task.name
    },{
        preserveScroll:true,
        onSuccess: () => {
            editingTaskId.value=null
        }
    })
}

function cancelUpdate(task){
    editingTaskId.value = null
}

function deleteTask(taskId) {
    router.delete(`/tasks/${taskId}`)
}

const filteredProjects = computed(() => {
    let prj = props.projects
    //project filter
    if(selectedProject.value !== 'all'){
        prj = prj.filter(
            project => project.id == selectedProject.value
        )
    }

    // ✅ completion filter
    if (selectedCompletion.value !== 'all') {
        prj = prj.map(project => ({
            ...project,
            tasks: project.tasks.filter(task =>
                selectedCompletion.value === 'completed'
                    ? task.completed
                    : !task.completed
            )
        }))
    }
        
    // tag filter
    if(selectedTaskTags.value !== 'all'){
        prj = prj.map(project => ({
            ...project,
            tasks: project.tasks.filter(task =>
                task.tags?.some(tag => tag.id == selectedTaskTags.value)
            )
        }))
    }

    //search filter
    if(searchQuery.value.trim()){
        const q = searchQuery.value.toLowerCase()
        prj = prj.map(project => ({
            ...project,
            tasks: project.tasks.filter(task =>
                task.name.toLowerCase().includes(q) ||  //task name
                project.name.toLowerCase().includes(q) ||//project name
                task.tags?.some(tag => tag.name.toLowerCase().includes(q)) ||//tag name
                (task.due_date && task.due_date.toString().replace(/[-\/]/g, '').includes(q.replace(/[-\/]/g, ''))) //due date
            )
        })).filter(project =>
            project.name.toLowerCase().includes(q) || //keep column if project name matches
            project.tasks.length >0  //or has matching tasks
     )
        
    }

    return prj
})




function toggleCompleted(task){
    router.put(`/tasks/${task.id}`, {
        completed: !task.completed
    })
}

function isOverdue(task){
    if (!task.due_date || task.completed) return false
    return new Date(task.due_date)< new Date()
}

function updateDueDate(task, value){
    router.put(`/tasks/${task.id}`, {
        due_date: value || null
    })
}

function formatDate(date){
    if(!date) return ''
    return date.substring(0, 10)
}



function toggleTaskTag(task, tag){
    if(!task.tags){
        task.tags = []
    }
    const exists = task.tags.find(t => t.id === tag.id)
    
    if(exists){
        task.tags = task.tags.filter(t => t.id !== tag.id)
    }else{
        task.tags.push(tag)
    }
    updateTaskTags(task)
}

function updateTaskTags(task){
    //alert(tag.id);
    //console.log(task.tags)
    router.put(`/tasks/${task.id}`,{
        tags: task.tags.map(tag => tag.id)
    },{
        preserveScroll: true
    })
}

function toggleTaskTagDropdown(taskId) {
    openTagTaskId.value = openTagTaskId.value === taskId ? null : taskId
}

function setTagDropdown(el, taskId){
    console.log(el);
    //console.log(openTagTaskId.value);
    if(openTagTaskId.value === taskId){
        tagDropdown.value = el
    }
}

function setTagFilterDropdown(el) {
    console.log(el);
    if (showTagFilterDropdown.value) {
        tagFilterDropdownEl.value = el
    }
}

function editInlineTask(task) {
    editingTaskId.value = task.id
    // no setTimeout needed anymore
}

function focusTaskInput() {
    nextTick(() => {
        const input = document.querySelector(`[data-task-input="${editingTaskId.value}"]`)
        input?.focus()
        input?.select()
    })
}

function formatCreatedDate(dateString) {
    if (!dateString) return ''
    return new Date(dateString).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    })
}


</script>


<template>
    
    <div :class="['app-shell', {'dark-theme': darkMode}]">
        <div class="container mx-auto p-4">
        

            <!---log out-->
           <div class="topbar">
            <div class="topbar-left">
                <span class="app-logo">✦ Taskboard</span>
            </div>
            <div class="topbar-right">
                <label class="theme-switch">
                <input type="checkbox" v-model="darkMode">
                <span class="switch-track">
                    <span class="switch-thumb">
                    <span class="icon sun">☀️</span>
                    <span class="icon moon">🌙</span>
                    </span>
                </span>
                </label>
                <form @submit.prevent="router.post(route('logout'))">
                <button type="submit" class="btn btn-red">Logout</button>
                </form>
            </div>
            </div>
            <!-----Create Project-->
            
            <div class="app-layout">
                <!--left side bar-->
                <aside class="sidebar">
                    Projects
                    <h2 class="mt-4">Create Project</h2>
                    <form @submit.prevent="createProject" class="create-project-form">
                        <input v-model="newProject" placeholder="Project name" class="h-10">
                        <button type="submit" class="btn btn-blue">Add</button>
                    </form>
                    <hr class="mt-4">
                    <!---progress bar-->
                    <h2 class="progress-section mt-5">Progress bar for tasks</h2>
                    <div v-for="project in props.projects" :key="project.id" class="project-progress-item">
                        <div class="progress-label">
                            <span>
                                {{ project.name }} Project
                            </span>
                            <span class="progress-count">
                                {{ project.completed_tasks_count }}/{{ project.tasks_count }}
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 h-2 rounded">
                            <div
                                class="bg-green-500 h-2 rounded"
                                :style="{
                                    width: project.tasks_count
                                        ? (project.completed_tasks_count / project.tasks_count * 100) + '%'
                                        : '0%'
                                }"
                            ></div>
                        </div>
                    </div>
                </aside>
                <!--main board-->
                <main class="board">
                    <div class="board-header">
                        <h2 class="board-title">Tasks</h2>
                    </div>
                    <!---Create Task-->
                    <h2 class="mt-4">Create Task</h2>
                    <form @submit.prevent="createTask" ref="taskForm" class="create-task-form">
                        <div class="flex gap-3 py-0.5">
                            <input v-model="newTask" placeholder="Task name" required ref="taskNameInput">
                            <select v-model="selectedProjectForTask" required >
                                <option value="">Select project</option>
                                <option v-for="projectForTask in props.projects" :key="projectForTask.id" :value="projectForTask.id">
                                    {{ projectForTask.name }}
                                </option>
                            </select>
                            <input 
                                type="date" 
                                v-model="newDueDate" 
                                required
                                
                            />
                            
                        
                            <div class="relative w-full h-10">
                                <!----dropdown trigger-->
                                <button 
                                    type="button"
                                    @click="showTagDropdown= !showTagDropdown"
                                    class="createTaskTags h-10"
                                
                                >
                                    {{ selectedTags.length ? selectedTags.length + ' tags selected' : 'Select Tags' }}
                                </button>
                                <!---dropdown-->
                                <div
                                    v-if="showTagDropdown"
                                    class="absolute mt-2 w-150 bg-white border rounded shadow-lg p-3 z-50 max-h-60 overflow-y-auto"
                                    

                                >

                                    <!---tag list-->
                                    <div
                                        v-for="tag in (props.tags ?? [])"
                                        :key="'tag-' + tag.id"
                                        class="tag-pill flex items-center gap-2 py-1 cursor-pointer"
                                    >
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                :value="tag.id"
                                                v-model="selectedTags"
                                                @keydown.enter.prevent
                                            >
                                             <span
                                                    style="width:10px; height:10px; border-radius:50%; flex-shrink:0;"
                                                    :style="{ backgroundColor: tag.color }"
                                             ></span>

                                            <span
                                                style="font-size:13px; color: var(--text-primary);"
                                            >
                                                {{ tag.name }}
                                            </span>
                                        </label>
                                        <button
                                            
                                            @click.stop.prevent="deleteTag(tag.id)"
                                            class="text-xs ml-2 opacity-70 hover:opacity-100"
                                        >
                                            X
                                        </button>

                                    
                                    </div>

                                    <hr class="my-2">

                                    <!--add new tag-->
                                    <button
                                        type="button"
                                        @click="openTagModal"
                                        class="text-blue-600 text-sm hover:underline"
                                    >
                                        + Add new tag
                                    </button>
                                </div>
                            </div>
                        
                            <button type="submit" class="btn btn-blue h-10">Add</button>
                            
                        </div>
                    </form>

                    <hr class="mt-4">

                    <!---tasks list-->
                    <div class="board-header">
                        <h2 class="board-title">Tasks</h2>
                    </div>
                    <div class="filters-row">
                        <!-----Project Dropdown filter-->
                        
                        <form class="mt-2">
                            <select v-model="selectedProject">
                                <option value="all">All Projects</option>
                                <option v-for="project in props.projects" :key="project.id" :value="project.id">
                                    {{ project.name }}
                                </option>
                            </select>
                        </form>
                        <!--complted and pending tasks-->
                        <form class="mt-2">
                            <select v-model="selectedCompletion">
                                <option value="all">All Tasks</option>
                                <option value="pending">⏳ Pending</option>
                                <option value="completed">✓ Completed</option>
                            </select>
                        </form>
                        <!--tag filter-->
                       

                        <div class="relative" ref="tagFilterDropdownEl">

                            <!-- trigger button -->
                            <button
                                type="button"
                                @click="showTagFilterDropdown = !showTagFilterDropdown"
                                class="h-9 border rounded px-2 flex items-center gap-2 mt-2"
                                style="font-size:12px; background: var(--bg-input); border: 1px solid var(--border); color: var(--text-secondary); min-width: 100px; border-radius: var(--radius-sm);"
                            >
                                <!-- show selected tag dot + name OR 'All Tags' -->
                                <template v-if="selectedTaskTags === 'all'">
                                    All Tags
                                </template>
                                <template v-else>
                                    <span
                                        :style="{
                                            width: '8px', height: '8px',
                                            borderRadius: '50%', flexShrink: '0',
                                            backgroundColor: props.tags.find(t => t.id == selectedTaskTags)?.color
                                        }"
                                    ></span>
                                    <span>{{ props.tags.find(t => t.id == selectedTaskTags)?.name }}</span>
                                </template>
                                <span style="margin-left:auto; font-size:10px;">▾</span>
                            </button>

                            <!-- dropdown -->
                            <div
                                    v-if="showTagFilterDropdown"
                                    class="absolute mt-1 z-50 rounded shadow-lg py-1"
                                    style="background: var(--bg-surface); border: 1px solid var(--border); min-width: 140px; top: 100%; left: 0;"
                            >
                                    <!-- All Tags option -->
                                    <div
                                        @click="selectedTaskTags = 'all'; showTagFilterDropdown = false"
                                        @mouseenter="hoveredTag = 'all'"
                                        @mouseleave="hoveredTag = null"
                                        class="flex items-center gap-2 px-3 py-1.5 cursor-pointer"
                                        :style="{ 
                                            background: hoveredTag === 'all' || selectedTaskTags === 'all'
                                                ? 'var(--accent-soft)'
                                                : 'transparent',
                                            color: hoveredTag === 'all' ? 'var(--accent)' : 'var(--text-secondary)'
                                        }"
                                        style="font-size:12px; color: var(--text-secondary);"
                                    >
                                        <span style="width:8px; height:8px; border-radius:50%; background: var(--border-hover); flex-shrink:0;"></span>
                                        All Tags
                                    </div>

                                    <!-- tag options -->
                                    <div
                                        v-for="tag in props.tags"
                                        :key="tag.id"
                                        @click="selectedTaskTags = tag.id; showTagFilterDropdown = false"
                                        @mouseenter="hoveredTag = tag.id"
                                        @mouseleave="hoveredTag = null"
                                        class="flex items-center gap-2 px-3 py-1.5 cursor-pointer"
                                        :style="{ 
                                            background: hoveredTag === tag.id || selectedTaskTags == tag.id
                                                ? 'var(--accent-soft)'
                                                : 'transparent',
                                            color: hoveredTag === tag.id ? 'var(--accent)' : 'var(--text-primary)',
                                            borderRadius: 'var(--radius-sm)',
                                            transition: 'background .12s, color .12s'
                                        }"
                                        style="font-size:12px; color: var(--text-primary);"
                                    >
                                        <span
                                            :style="{
                                                width: '8px', height: '8px',
                                                borderRadius: '50%', flexShrink: '0',
                                                backgroundColor: tag.color
                                            }"
                                        ></span>
                                        {{ tag.name }}
                                    </div>
                            </div>
                        </div>

                        <div class="mt-2 relative">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="🔍 Search..."
                                
                            />
                            <button
                                v-if="searchQuery"
                                @click="searchQuery = ''"
                                class="absolute right-1 top-1 text-gray-400 hover:text-gray-600"
                                style="font-size: 10px; background: none; border: none;"
                            >✕</button>
                        </div>
                    </div>
                    <!--tasklist for project drag-->
                    <div class="kanban-board">
                        <div
                            v-for="project in filteredProjects"
                            :key="project.id"
                            class="kanban-column"
                        >
                            <div class="kanban-header">
                                <h3>{{ project.name }}</h3>
                            </div>
                            <!---tasklist for priority drag-->
                            <ul class="taskList kanban-tasks"
                                :data-project="project.id"    
                            >
                                <li v-for="task in project.tasks" 
                                    :key="task.id" 
                                    :data-id="task.id" 
                                    :class="[
                                        'kanban-card',
                                        task.completed ? 'opacity-50' : '',
                                        isOverdue(task) ? 'border-red-500 bg-red-50' : ''
                                    ]"

                                >
                                    <div class="flex items-center gap-4 w-full">
                                            <!----Completed checkbox-->
                                            <input type="checkbox" :checked="task.completed" @change="toggleCompleted(task)">
                                            <div class="flex-1">
                                                <div class=" task-title font-bold mb-2 text-sm cursor-pointer w-fit" >
                                                    <transition name="edit-fade" mode="out-in" @after-leave="focusTaskInput">
                                                        <span class="task-name" v-if="editingTaskId !== task.id" :key="'text-'+task.id" @click.prevent="editInlineTask(task)">
                                                            {{ task.priority }}. {{ task.name }}
                                                            <span class="edit-icon">✏️</span>
                                                        </span>
                                                        <input 
                                                            v-model="task.name" 
                                                            v-else 
                                                            :key="'input-'+task.id"
                                                            :data-task-input="task.id"
                                                            tabindex="0"
                                                            class="task-edit-input" 
                                                            @keydown.enter="updateTask(task)"
                                                            @keydown.escape="cancelUpdate(task)"
                                                        >

                                                        
                                                    </transition>
                                                </div>

                                                <!--due date-->
                                                <input  
                                                    type="date"
                                                    :value="formatDate(task.due_date)"
                                                    @change="updateDueDate(task, $event.target.value)"
                                                    class="text-sm border rounded px-1 py-0.5"
                                                >
                                            </div>
                                            <div class="flex gap-2">
                                                <!---update task button-->
                                                
                                                <button @click.prevent="updateTask(task)" v-if="editingTaskId === task.id" class="btn btn-green">Save</button>

                                                <!---Delete Task-->
                                                <button @click.prevent="deleteTask(task.id)" class="btn btn-red">X</button>
                                                
                                                <!---display tags of tasks-->
                                                <div class="relative" >
                                                    <div class="flex gap-2 flex-wrap cursor-pointer mt-1"
                                                        @click.stop="toggleTaskTagDropdown(task.id)">
                                                        
                                                        <span
                                                            v-for="tags in task.tags"
                                                            :key="tags.id"
                                                            class="text-xs px-2 py-0.5 rounded tagSpan"
                                                            :style="{ backgroundColor: tags.color}"
                                                        >
                                                            
                                                            {{ tags.name }}
                                                        </span>
                                                        <span class="text-xs text-gray-500">+ tag</span>
                                                    </div>
                                                    <!---dropdown-->
                                                    <div v-if="openTagTaskId === task.id" 
                                                        :ref="el => setTagDropdown(el, task.id)"
                                                        class="task-tags">
                                                        <label
                                                            v-for="taskTag in props.tags"
                                                            :key="taskTag.id"
                                                            class="flex items-center gap-2 py-1 cursor-pointer"
                                                        >
                                                            <input type="checkbox"
                                                            :checked="task.tags.some(t => t.id === taskTag.id)" 
                                                            @change="toggleTaskTag(task, taskTag)">


                                                            <span
                                                                style="width:10px; height:10px; border-radius:50%; flex-shrink:0;"
                                                                :style="{ backgroundColor: taskTag.color }"
                                                            ></span>
                                                  
                                                            <span
                                                                class="tag"
                                                                style="font-size:13px; color: var(--text-primary);">
                                                                    
                                                                {{ taskTag.name }}
                                                            </span>
                                                        </label>
                                                    </div>

                                                </div>
                                            </div>
                                    </div>
                                </li>
                            </ul>
                            <div
                                v-if="project.tasks.length === 0"
                                class="empty"
                                >
                                No tasks
                            </div>
                        </div>
                    </div>
                </main>

                <!---activity sidebar-->
                <aside class="activity-sidebar">
                    <h3 class="activity-title">Activity</h3>

                    <div class="activity-feed">

                        <div
                            v-for="activity in activities"
                            :key="activity.id"
                            class="activity-item"
                        >
                            <div class="activity-text">
                                <span v-if="activity.action === 'user_registered'">
                                    🎉 <b>{{ activity.meta.name }}</b> joined
                                </span>

                                <span v-if="activity.action === 'user_loggedin'">
                                    👋 <b>{{ activity.meta.name }}</b> logged in
                                </span>

                                <span v-if="activity.action === 'user_loggedout'">
                                    🚪 <b>{{ activity.meta.name }}</b> logged out
                                </span>

                                <span v-if="activity.action === 'project_created'">
                                    📁 Project <b>{{ activity.meta.project_name }}</b> created
                                </span>

                                <span v-if="activity.action === 'project_deleted'">
                                    🗑️ Project <b>{{ activity.meta.project_name }}</b> deleted
                                </span>

                                <span v-if="activity.action === 'task_created'">
                                    ✏️ Created Task <b><i>{{ activity.meta.task_name }}</i></b> in <b><i>{{ activity.meta.project_name }}</i></b> Project
                                </span>

                                <span v-if="activity.action === 'task_moved'">
                                    🔀 Moved <i>{{ activity.meta.task_name }}</i>&nbsp; <b>({{ activity.meta.from }} → {{ activity.meta.to }})</b>
                                </span>

                                <span v-if="activity.action === 'task_reordered'">
                                    🔃 Changed Priority of <i>{{ activity.meta.task_name }}</i>&nbsp; <b>({{ activity.meta.new_order }} → {{ activity.meta.old_order }})</b>&nbsp; of Project <i>{{ activity.meta.project_name }}</i>
                                </span>

                                <span v-if="activity.action === 'task_deleted'">
                                    🗑️ Task <b><i>{{ activity.meta.task_name }}</i></b> Deleted from <b><i>{{ activity.meta.project_name }}</i></b> Project
                                </span>

                                <span v-if="activity.action === 'task_completed'">
                                    ✅ Task <b><i>{{ activity.meta.task_name }}</i></b> completed in <b><i>{{ activity.meta.project_name }}</i></b> Project
                                </span>

                                <span v-if="activity.action === 'task_uncompleted'">
                                    🔄 Task <b><i>{{ activity.meta.task_name }}</i></b> completion revoked in <b><i>{{ activity.meta.project_name }}</i></b> Project
                                </span>

                                <span v-if="activity.action === 'task_renamed'">
                                    ✍️ Task Renamed <b>({{ activity.meta.from }} → {{ activity.meta.to }})</b>
                                </span>

                                <span v-if="activity.action === 'task_tags_updated'">
                                    🏷️ Tags updated of <b><i>{{ activity.meta.task_name }}</i></b> from <b><i>{{ activity.meta.project_name }}</i></b>
                                </span>

                                <span v-if="activity.action === 'due_date_changed'">
                                    📅 Due date updated of <i>{{ activity.meta.task_name }}</i> <b>({{ activity.meta.from }} → {{ activity.meta.to }})</b> from <i>{{ activity.meta.project_name }}</i>
                                </span>
                            </div>

                            <div class="activity-time">
                                {{ formatCreatedDate(activity.created_at) }}
                            </div>
                        
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </div>

    <div v-if="showTagModal" class="fixed inset-0 flex items-center justify-center bg-black/40">
        <div class="bg-white p-6 eounded w-96">
            <h3 class="text-lg font-semibold mb-4">
                Create Tag
            </h3>

            <input
                v-model="newTag"
                type="text"
                placeholder="Tag name"
                class="border w-full p-2 rounded mb-4"
            />
            <input
                type="color"
                v-model="newTagColor"
                class="w-12 h-10"
            />

            <div class="flex justify-end gap-2">
                <button
                    @click="showTagModal=false"
                    class="px-3 py-1 border rounded"
                >
                    Cancel
                </button>

                <button
                    @click="createTag"
                    class="px-3 py-1 bg-blue-600 text-white rounded"
                >
                    Save
                </button>
            </div>
        </div>
    </div>
</template>



<style scoped>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap');

/* ── CSS VARIABLES - LIGHT THEME ── */
.app-shell {
  --font: 'DM Sans', sans-serif;
  --mono: 'DM Mono', monospace;
  --radius-sm: 8px;
  --radius-md: 12px;
  --radius-lg: 16px;
  --radius-xl: 24px;

  --bg-page: #F4F2EE;
  --bg-surface: #FFFFFF;
  --bg-sidebar: #FAFAF8;
  --bg-card: #FFFFFF;
  --bg-input: #F7F6F3;
  --bg-hover: #F0EEE9;

  --text-primary: #1A1919;
  --text-secondary: #6B6860;
  --text-muted: #A09D97;

  --border: rgba(26,25,25,0.08);
  --border-hover: rgba(26,25,25,0.16);

  --accent: #2D5BE3;
  --accent-soft: #EEF2FD;

  --success: #16A34A;
  --success-soft: #DCFCE7;
  --danger: #DC2626;
  --danger-soft: #FEE2E2;
  --warning: #D97706;
  --warning-soft: #FEF3C7;

  --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
  --shadow-md: 0 4px 12px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.04);
  --shadow-lg: 0 8px 24px rgba(0,0,0,0.1), 0 4px 8px rgba(0,0,0,0.06);

  font-family: var(--font);
  min-height: 100vh;
  color: var(--text-primary);
  transition: background .3s, color .3s;

  background: var(--bg-page);
}


:deep(input[type="checkbox"]) {
  appearance: none;
  -webkit-appearance: none;
  width: 15px;
  height: 15px;
  border-radius: 4px;
  border: 1.5px solid var(--border-hover);
  background: var(--bg-input);
  cursor: pointer;
  position: relative;
  transition: background .15s, border-color .15s;
}

:deep(input[type="checkbox"]:checked) {
  background: black !important;
  background-image: none !important;
  border-color: black !important;
}

:deep(input[type="checkbox"]:checked::after) {
  content: '';
  position: absolute;
  left: 4px;
  top: 1px;
  width: 4px;
  height: 8px;
  border: 2px solid white;  /* ← always white, works on any currentColor bg */
  border-top: none;
  border-left: none;
  transform: rotate(45deg);
}

/* ── CSS VARIABLES - DARK THEME ── */
.dark-theme.app-shell {
  --bg-page: #0b1120;
  --bg-surface: #111827;
  --bg-sidebar: #0f172a;
  --bg-card: #1e293b;
  --bg-input: #1e293b;
  --bg-hover: #273449;

  --text-primary: #f1f5f9;
  --text-secondary: #94a3b8;
  --text-muted: #475569;

  --border: rgba(255,255,255,0.07);
  --border-hover: rgba(255,255,255,0.14);

  --accent: #5B87F0;
  --accent-soft: #1a2545;

  --success: #22c55e;
  --success-soft: #052e16;
  --danger: #ef4444;
  --danger-soft: #2d0a0a;
  --warning: #f59e0b;
  --warning-soft: #2d1a00;

  --shadow-sm: 0 1px 3px rgba(0,0,0,0.4);
  --shadow-md: 0 4px 12px rgba(0,0,0,0.5);
  --shadow-lg: 0 8px 24px rgba(0,0,0,0.6);

  color: var(--text-primary);
  background: linear-gradient(180deg, #020617 0%, #0f172a 40%, #020617 100%);
}

/* ── LAYOUT ── */
.app-layout {
  display: grid;
  grid-template-columns: 220px minmax(0, 1fr) 280px;
  height: 100vh;
}

.sidebar {
  background: var(--bg-sidebar);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  transition: background .3s;
  padding: 1em;
}

.board {
  background: transparent;
  overflow: auto;
  -ms-overflow-style: none;
  scrollbar-width: none;
  padding: 15px;
}

.board::-webkit-scrollbar { display: none; }

.activity-sidebar {
  background: var(--bg-sidebar);
  border-left: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  overflow-x: auto;
  transition: background .3s;
  padding: 1em;
}

/* ── TOPBAR ── */
.topbar {
  height: 56px;
  background: var(--bg-surface);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
  box-shadow: var(--shadow-sm);
  flex-shrink: 0;
  transition: background .3s;
}

.topbar-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 8px;
}

.app-logo {
  font-size: 15px;
  font-weight: 600;
  color: var(--text-primary);
  letter-spacing: -0.3px;
}

/* ── BUTTONS ── */
.btn {
  font-family: var(--font);
  cursor: pointer;
  border-radius: var(--radius-sm);
  transition: all .15s;
  font-size: 13px;
}

.btn-blue {
  background: var(--accent);
  color: #fff;
  border: none;
  padding: 8px 14px;
  font-weight: 500;
}

.btn-blue:hover { opacity: .9; }
.btn-blue:active { transform: scale(.98); }

.btn-green {
  background: var(--success);
  color: #fff;
  border: none;
  padding: 5px 10px;
  font-size: 12px;
}

.btn-green:hover { opacity: .9; }
.btn-green:active { transform: scale(.98); }

.btn-red {
  background: none;
  color: var(--danger);
  border: 1px solid var(--border);
  padding: 5px 9px;
  font-size: 12px;
}

.btn-red:hover {
  background: var(--danger-soft);
  border-color: var(--danger);
}

.btn-red:active { transform: scale(.98); }

/* ── INPUTS ── */
input, select {
  font-family: var(--font);
  background: var(--bg-input);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  color: var(--text-primary);
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}

input:focus, select:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(45,91,227,0.1);
}

input::placeholder { color: var(--text-muted); }

/* ── THEME SWITCH ── */
.theme-switch {
  position: relative;
  display: inline-block;
  width: 60px;
  height: 30px;
  margin-right: 10px;
}

.theme-switch input { opacity: 0; width: 0; height: 0; }

.switch-track {
  position: absolute;
  cursor: pointer;
  inset: 0;
  background: var(--bg-hover);
  border: 1px solid var(--border-hover);
  border-radius: 30px;
  transition: .3s;
}

.switch-thumb {
  position: absolute;
  height: 24px;
  width: 24px;
  left: 3px;
  top: 3px;
  background: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  transition: .3s;
}

.icon { position: absolute; transition: opacity .2s; }
.moon { opacity: 0; }

input:checked + .switch-track { background: #1e3a5f; border-color: var(--accent); }
input:checked + .switch-track .switch-thumb { transform: translateX(30px); }
input:checked + .switch-track .sun { opacity: 0; }
input:checked + .switch-track .moon { opacity: 1; }

/* ── TAG PILL ── */
.tag-pill { transition: transform .15s ease, box-shadow .15s ease; }
.tag-pill:hover { transform: scale(1.05); box-shadow: 0 3px 8px rgba(0,0,0,0.15); }

/* ── CREATE PROJECT FORM ── */
.create-project-form input {
  margin-top: 10px;
  width: 100%;
  font-size: 12px;
}

.create-project-form button {
  margin-top: 10px;
  width: 100%;
  padding: 7px 10px;
  font-size: 12px;
}

/* ── BOARD HEADER ── */
.board-header {
  background: var(--bg-surface);
  border-bottom: 1px solid var(--border);
  padding: 14px 20px;
  flex-shrink: 0;
  transition: background .3s;
}

.board-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 14px;
  letter-spacing: -0.2px;
}

/* ── CREATE TASK FORM ── */
.create-task-form {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
}

.create-task-form input,
.create-task-form select {
  padding: 8px 10px;
  font-size: 12px;
}

.createTaskTags{
    padding: 8px 10px;
    font-size: 12px;
        font-family: var(--font);
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text-primary);
    outline: none;
    transition: border-color .15s, box-shadow .15s;
}
/* ── FILTERS ── */
.filters-row {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 0;
  flex-shrink: 0;
}

.filters-row select,
.filters-row input {
  padding: 6px 10px;
  font-size: 12px;
  color: var(--text-secondary);
  cursor: pointer;
  min-width: 100px;
}

.filter-tag option { color: white; }

/* ── KANBAN ── */
.kanban-board {
  display: flex;
  gap: 20px;
  overflow-x: auto;
  -ms-overflow-style: none;
  scrollbar-width: none;
  padding: 20px;
}

.kanban-board::-webkit-scrollbar { display: none; }

.kanban-column {
  min-width: 80%;
  background: var(--bg-surface);
  border-radius: 10px;
  border: 1px solid var(--border);
  padding: 12px;
  flex-shrink: 0;
  transition: background .3s, border-color .3s;
}

.kanban-header {
  padding: 12px 14px 10px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--border);
  font-weight: 600;
  font-size: 13px;
  color: var(--text-primary);
}

.count { font-size: 12px; color: var(--text-muted); }

.kanban-tasks {
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 7px;
  min-height: 60px;
  list-style: none;
  overflow-y: auto;
  max-height: calc(100vh - 280px);
}

/* ── TASK CARD ── */
.kanban-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  padding: 11px 12px;
  cursor: grab;
  transition: box-shadow .2s, border-color .2s, transform .15s, background .3s;
}

.kanban-card:hover {
  box-shadow: var(--shadow-md);
  border-color: var(--border-hover);
}

.kanban-card:active { cursor: grabbing; }

.kanban-card.dragging {
  opacity: .5;
  transform: scale(.98);
}

.taskList.drag-over {
  background: var(--accent-soft);
  border-radius: 8px;
}

/* ── TASK NAME ── */
.task-name {
  position: relative;
  cursor: pointer;
  transition: color .2s;
  color: var(--text-primary);
}

.task-name:hover { color: var(--accent); }

.edit-icon {
  position: absolute;
  margin-left: 5px;
  top: 50%;
  transform: translateY(-50%) translateX(6px);
  font-size: 12px;
  opacity: 0;
  transition: all .18s ease;
}

.task-name:hover .edit-icon {
  opacity: .7;
  transform: translateY(-50%) translateX(0);
}

/* ── TASK EDIT INPUT ── */
.task-edit-input {
  width: 100%;
  background: transparent;
  border: none;
  border-bottom: 2px solid var(--accent);
  outline: none;
  box-shadow: none;
  font-size: inherit;
  font-family: var(--font);
  color: var(--text-primary);
  padding: 2px 0;
}

.task-edit-input:focus { border-bottom-color: var(--text-muted); }


.border-red-500 {
    --tw-border-opacity: 1;
    border-color: rgb(239 68 68 / var(--tw-border-opacity, 1)) !important;
}
/* ── TAGS ── */
.tag {
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 4px;
  color: white;
}

.task-tags {
  position: absolute;
  margin-top: 4px;
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-lg);
  z-index: 9999;
  padding: 8px;
  min-width: 140px;
}

.task-tags label {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 5px 6px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: background .12s;
  color: var(--text-primary);
}

.task-tags label:hover { background: var(--bg-hover); }

/* ── EMPTY COLUMN ── */
.empty {
  text-align: center;
  color: var(--text-muted);
  font-size: 12px;
  padding: 20px 10px;
  border: 1px dashed var(--border);
  border-radius: var(--radius-md);
  margin: 4px;
}

/* ── TRANSITIONS ── */
.edit-fade-enter-active,
.edit-fade-leave-active { transition: opacity .18s ease, transform .18s ease; }
.edit-fade-enter-from,
.edit-fade-leave-to { opacity: 0; transform: translateY(2px); }

body, .kanban-column, .kanban-card { transition: background .25s ease, color .25s ease; }

/* ── PROGRESS BARS ── */
.project-progress-item { margin-bottom: 14px; }

.progress-label {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: var(--text-secondary);
  margin-bottom: 5px;
}

.progress-count {
  font-family: var(--mono);
  font-size: 11px;
  color: var(--text-muted);
}

.progress-track {
  height: 4px;
  border-radius: 99px;
  background: var(--border);
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  border-radius: 99px;
  background: linear-gradient(90deg, var(--accent), #6B9AF8);
  transition: width .4s ease;
}

.progress-fill.complete {
  background: linear-gradient(90deg, var(--success), #4ADE80);
}

/* ── ACTIVITY ── */
.activity-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-primary);
  border-bottom: 1px solid var(--border);
  padding-bottom: 12px;
  margin-bottom: 8px;
}

.activity-feed {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.activity-item {
  padding: 9px 10px;
  border-radius: var(--radius-md);
  transition: background .15s;
}

.activity-item:hover { background: var(--bg-hover); }

.activity-body {
  display: flex;
  gap: 9px;
  align-items: flex-start;
}

.activity-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--accent);
  flex-shrink: 0;
  margin-top: 4px;
}

.activity-text {
  font-size: 12px;
  color: var(--text-secondary);
  line-height: 1.5;
  flex: 1;
}

.activity-text b {
  color: var(--text-primary);
  font-weight: 500;
}

.activity-time {
  font-size: 10px;
  color: var(--text-muted);
  font-family: var(--mono);
  margin-top: 3px;
  padding-left: 16px;
}

/* ── TAG MODAL ── */
.modal-backdrop { backdrop-filter: blur(3px); }

/* ── SCROLLBARS ── */
::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border-hover); border-radius: 99px; }

/* ── DARK THEME — only overrides that can't use variables ── */
.dark-theme h2,
.dark-theme h3 { color: var(--text-primary); }

.dark-theme .kanban-column { box-shadow: 0 2px 6px rgba(0,0,0,.4); }

.dark-theme .kanban-card { box-shadow: 0 2px 6px rgba(0,0,0,.4); }
.dark-theme .kanban-card:hover { background: var(--bg-hover); }

.dark-theme .tag { color: white; }

/* fix tailwind overrides in dark mode */
.dark-theme .bg-gray-200 { background: var(--bg-hover) !important; }
.dark-theme .bg-white { background: var(--bg-surface) !important; }
.dark-theme .bg-red-50 { background: var(--danger-soft) !important; }
.dark-theme .text-gray-500 { color: var(--text-muted) !important; }
.dark-theme .border { border-color: var(--border) !important; }
.dark-theme .shadow-lg { box-shadow: var(--shadow-lg) !important; }
.dark-theme input,
.dark-theme select { color: var(--text-primary); }

</style>