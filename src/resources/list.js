/*
  Requirement: Populate the "Course Resources" list page.

  Instructions:
  1. Link this file to `list.html` using:
     <script src="list.js" defer></script>

  2. In `list.html`, add id="resource-list-section" to the
     <section> element that will contain the resource articles.

  3. Implement the TODOs below.
*/

// --- Element Selections ---
// TODO: Select the section for the resource list ('#resource-list-section').
const resourceListSection = document.querySelector('#resource-list-section');

// --- Functions ---

/**
 * TODO: Implement the createResourceArticle function.
 * It takes one resource object { id, title, description, link }.
 * It should return an <article> element matching the structure in `list.html`.
 * The "View Resource & Discussion" link's `href` MUST be set to
 * `details.html?id=${id}` so the detail page knows which resource to load.
 */
function createResourceArticle(resource) {
    const article = document.createElement('article');

    const heading = document.createElement('h3');
    heading.textContent = resource.title;

    const descriptionPara = document.createElement('p');
    descriptionPara.textContent = resource.description;

    const link = document.createElement('a');
    link.href = `details.html?id=${resource.id}`;
    link.textContent = 'View Resource & Discussion';

    article.appendChild(heading);
    article.appendChild(descriptionPara);
    article.appendChild(link);

    return article;
}

/**
 * TODO: Implement the loadResources function.
 * This function must be 'async'.
 * It should:
 * 1. Use `fetch()` to GET data from the API endpoint:
 *    './api/index.php'
 * 2. Parse the JSON response. The API returns { success: true, data: [...] }.
 * 3. Clear any existing content from the list section.
 * 4. Loop through the resources array in `data`. For each resource:
 *    - Call `createResourceArticle()` with the resource object.
 *    - Append the returned <article> element to the list section.
 */
async function loadResources() {
    try {
        const response = await fetch('./api/index.php');
        if (!response.ok) throw new Error('HTTP error ' + response.status);
        const result = await response.json();

        if (result.success) {
            resourceListSection.innerHTML = '';
            result.data.forEach(resource => {
                const resourceArticle = createResourceArticle(resource);
                resourceListSection.appendChild(resourceArticle);
            });
        } else {
            throw new Error(result.message || 'Failed to load resources');
        }
    } catch (error) {
        console.error("Error loading resources:", error);
        resourceListSection.innerHTML = '<p>Failed to load resources. Please try again later.</p>';
    }
}

// --- Initial Page Load ---
// Call the function to populate the page.
loadResources();
