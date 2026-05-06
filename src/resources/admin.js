// --- Global Data Store ---
// This will hold the resources loaded from the API.
let resources = [];
let editMode = false;
let editId = null;


// --- Element Selections ---
// TODO: Select the resource form ('#resource-form').
const form = document.querySelector('#resource-form');

// TODO: Select the resources table body ('#resources-tbody').
const tbody = document.querySelector('#resources-tbody');


// --- Functions ---

function createResourceRow(resource) {
    const tr = document.createElement('tr');

    const tdTitle = document.createElement('td');
    tdTitle.textContent = resource.title;

    const tdDesc = document.createElement('td');
    tdDesc.textContent = resource.description;

    const tdLink = document.createElement('td');
    const a = document.createElement('a');
    a.href = resource.link;
    a.textContent = resource.link;
    a.target = "_blank";
    tdLink.appendChild(a);

    const tdActions = document.createElement('td');

    const editBtn = document.createElement('button');
    editBtn.textContent = "Edit";
    editBtn.classList.add('edit-btn');
    editBtn.dataset.id = resource.id;

    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = "Delete";
    deleteBtn.classList.add('delete-btn');
    deleteBtn.dataset.id = resource.id;

    tdActions.appendChild(editBtn);
    tdActions.appendChild(deleteBtn);

    tr.appendChild(tdTitle);
    tr.appendChild(tdDesc);
    tr.appendChild(tdLink);
    tr.appendChild(tdActions);

    return tr;
}

function renderTable() {
    while (tableBody.firstChild) {
        tableBody.removeChild(tableBody.firstChild);
    }

    resources.forEach(resource => {
        const row = createResourceRow(resource);
        tableBody.appendChild(row);
    });
}

function handleAddResource(event) {
    event.preventDefault();

    const title = document.querySelector('#resource-title').value;
    const description = document.querySelector('#resource-description').value;
    const link = document.querySelector('#resource-link').value;

    if (editMode) {
        fetch('./api/index.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: editId, title, description, link })
        })
        .then(res => res.json())
        .then(() => {
            resources = resources.map(r => 
                r.id == editId ? { id: editId, title, description, link } : r
            );
            renderTable();
            form.reset();
            editMode = false;
            editId = null;
            document.querySelector('#add-resource').textContent = "Add Resource";
        });

        return;
    }

    fetch('./api/index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, description, link })
    })
    .then(res => res.json())
    .then(data => {
        resources.push({ id: data.id, title, description, link });
        renderTable();
        form.reset();
    });
}

function handleTableClick(event) {
    const id = event.target.dataset.id;

    if (event.target.classList.contains('delete-btn')) {
        fetch(`./api/index.php?id=${id}`, {
            method: 'DELETE'
        })
        .then(res => res.json())
        .then(() => {
            resources = resources.filter(r => r.id != id);
            renderTable();
        });
    }

    if (event.target.classList.contains('edit-btn')) {
        const resource = resources.find(r => r.id == id);

        document.querySelector('#resource-title').value = resource.title;
        document.querySelector('#resource-description').value = resource.description;
        document.querySelector('#resource-link').value = resource.link;

        editMode = true;
        editId = id;

        document.querySelector('#add-resource').textContent = "Update Resource";
    }
}

async function loadAndInitialize() {
    const res = await fetch('./api/index.php');
    const data = await res.json();

    resources = data.data;

    renderTable();

    form.addEventListener('submit', handleAddResource);
    tbody.addEventListener('click', handleTableClick);
}


// --- Initial Page Load ---
// Call the main async function to start the application.
loadAndInitialize();
