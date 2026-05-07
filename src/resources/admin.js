/*
  Requirement: Make the "Manage Resources" page interactive.

  Instructions:
  1. Link this file to `admin.html` using:
     <script src="admin.js" defer></script>
  
  2. In `admin.html`, add id="resources-tbody" to the <tbody> element
     inside your resources-table. This id is required by this script.
  
  3. Implement the TODOs below.
*/

// --- Global Data Store ---
window.resources = window.resources || [];
var resources = window.resources;
let editMode = false;
let editId = null; 

const form = document.querySelector('#resource-form');
const submitBtn = document.querySelector('#add-resource');

/**
 * TODO: Implement the createResourceRow function.
 * It takes one resource object { id, title, description, link }.
 * It should return a <tr> element with the following <td>s:
 * 1. A <td> for the title.
 * 2. A <td> for the description.
 * 3. A <td> for the link.
 * 4. A <td> containing two buttons:
 *    - An "Edit" button with class="edit-btn" and data-id="${id}".
 *    - A "Delete" button with class="delete-btn" and data-id="${id}".
 */
function createResourceRow(resource) {
    const tr = document.createElement('tr');

    const tdTitle = document.createElement('td');
    tdTitle.textContent = resource.title;
    tr.appendChild(tdTitle);

    const tdDescription = document.createElement('td');
    tdDescription.textContent = resource.description;
    tr.appendChild(tdDescription);

    const tdLink = document.createElement('td');
    const a = document.createElement('a');
    a.href = resource.link;
    a.textContent = resource.link;
    a.target = '_blank';
    tdLink.appendChild(a);
    tr.appendChild(tdLink);

    const tdActions = document.createElement('td');

    const editBtn = document.createElement('button');
    editBtn.textContent = 'Edit';
    editBtn.classList.add('edit-btn');
    editBtn.dataset.id = resource.id;

    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = 'Delete';
    deleteBtn.classList.add('delete-btn');
    deleteBtn.dataset.id = resource.id;

    tdActions.appendChild(editBtn);
    tdActions.appendChild(deleteBtn);
    tr.appendChild(tdActions);

    return tr;
}

/**
 * TODO: Implement the renderTable function.
 * It should:
 * 1. Clear the resources table body ('#resources-tbody').
 * 2. Loop through the global `resources` array.
 * 3. For each resource, call `createResourceRow()` and
 *    append the returned <tr> to the table body.
 */
function renderTable() {
    const tbody = document.getElementById('resources-tbody');
    if (!tbody) return;

    tbody.innerHTML = '';

    const data = (Array.isArray(resources) && resources.length)
        ? resources
        : window.resources;

    data.forEach(resource => {
        const row = createResourceRow(resource);
        tbody.appendChild(row);
    });
}

/**
 * TODO: Implement the handleAddResource function.
 * This is the event handler for the form's 'submit' event.
 * It should:
 * 1. Prevent the form's default submission.
 * 2. Get the values from the title (id="resource-title"),
 *    description (id="resource-description"), and
 *    link (id="resource-link") inputs.
 * 3. Use `fetch()` to POST the new resource to the API:
 *    - URL: './api/index.php'
 *    - Method: POST
 *    - Headers: { 'Content-Type': 'application/json' }
 *    - Body: JSON.stringify({ title, description, link })
 * 4. The API returns { success: true, id: <new id> }.
 *    Add the new resource object (including the id returned by the API)
 *    to the global `resources` array.
 * 5. Call `renderTable()` to refresh the list.
 * 6. Reset the form.
 */
async function handleAddResource(event) {
    event.preventDefault();

    const title = document.querySelector('#resource-title').value;
    const description = document.querySelector('#resource-description').value;
    const link = document.querySelector('#resource-link').value;

    if (editMode) {
        const response = await fetch('./api/index.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: editId, title, description, link })
        });
        const result = await response.json();

        if (result.success) {
            const index = resources.findIndex(r => r.id == editId);
            resources[index] = { id: editId, title, description, link };
            editMode = false;
            editId = null;
            submitBtn.textContent = 'Add Resource';
        }
    } else {
        const response = await fetch('./api/index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title, description, link })
        });
        const result = await response.json();

        if (result.success) {
            resources.push({ id: result.id, title, description, link });
        }
    }

    window.resources = resources;
    renderTable();
    form.reset();
}

/**
 * TODO: Implement the handleTableClick function.
 * This handles click events on the table body using event delegation.
 */
async function handleTableClick(event) {
    const id = event.target.dataset.id;

    if (event.target.classList.contains('delete-btn')) {
        const response = await fetch(`./api/index.php?id=${id}`, {
            method: 'DELETE'
        });
        const result = await response.json();

        if (result.success) {
            resources = resources.filter(r => r.id != id);
            window.resources = resources;
            renderTable();
        }
    }

    if (event.target.classList.contains('edit-btn')) {
        const resource = resources.find(r => r.id == id);

        document.querySelector('#resource-title').value = resource.title;
        document.querySelector('#resource-description').value = resource.description;
        document.querySelector('#resource-link').value = resource.link;

        editMode = true;
        editId = id;
        submitBtn.textContent = 'Update Resource';
    }
}

/**
 * TODO: Implement the loadAndInitialize function.
 */
async function loadAndInitialize() {
    const response = await fetch('./api/index.php');
    const result = await response.json();

    if (result.success) {
        resources = result.data;
        window.resources = resources;
        renderTable();
    }

    form.addEventListener('submit', handleAddResource);
    document
        .querySelector('#resources-tbody')
        .addEventListener('click', handleTableClick);
}

// --- Initial Page Load ---
// Call the main async function to start the application.
loadAndInitialize();
