const listSection = document.querySelector('#week-list-section');

function createWeekArticle(week) {
  const article = document.createElement('article');

  const heading2 = document.createElement('h2');
  heading2.textContent = week.title;

  const pDate = document.createElement('p');
  pDate.textContent = `Starts on: ${week.startDate}`;

  const pDescription = document.createElement('p');
  pDescription.textContent = week.description;

  const link = document.createElement('a');
  link.href = `details.html?id=${week.id}`;
  link.textContent = 'View Details & Discussion';

  article.appendChild(heading2);
  article.appendChild(pDate);
  article.appendChild(pDescription);
  article.appendChild(link);

  return article;
}

async function loadWeeks() {
  if (!listSection) {
    console.error('Element #week-list-section not found');
    return;
  }

  try {
    const response = await fetch('weeks.json');

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const weeks = await response.json();

    listSection.innerHTML = '';

    weeks.forEach(week => {
      const article = createWeekArticle(week);
      listSection.appendChild(article);
    });

  } catch (error) {
    console.error('Failed to load weeks:', error);
    listSection.innerHTML = '<p class="error">Sorry, failed to load weekly breakdown. Please refresh.</p>';
  }
}

loadWeeks();  const pDescription = document.createElement('p');
  pDescription.textContent = week.description;

  const link = document.createElement('a');
  link.href = `details.html?id=${week.id}`; 
  link.textContent = 'View Details & Discussion';

  article.appendChild(heading2);
  article.appendChild(pDate);
  article.appendChild(pDescription);
  article.appendChild(link);

  return article;
}

/**
 * TODO: Implement the loadWeeks function.
 * This function needs to be 'async'.
 * It should:
 * 1. Use `fetch()` to get data from 'weeks.json'.
 * 2. Parse the JSON response into an array.
 * 3. Clear any existing content from `listSection`.
 * 4. Loop through the weeks array. For each week:
 * - Call `createWeekArticle()`.
 * - Append the returned <article> element to `listSection`.
 */
async function loadWeeks() {
  // ... your implementation here ...
  const response = await fetch('weeks.json');
  const weeks = await response.json();

  listSection.innerHTML = '';

  for (const week of weeks) {
    const article = createWeekArticle(week);
    listSection.appendChild(article);
  }
}

// --- Initial Page Load ---
// Call the function to populate the page.
loadWeeks(); *       <p>{description}</p>
 *       <a href="details.html?id={id}">View Details & Discussion</a>
 *     </article>
 *
 * Important: the href MUST be "details.html?id=<id>" (integer id from
 * the weeks table) so that details.js can read the id from the URL.
 */
function createWeekArticle(week) {
  // ... your implementation here ...
}

/**
 * TODO: Implement loadWeeks (async).
 *
 * It should:
 * 1. Use fetch() to GET data from './api/index.php'.
 *    The API returns JSON in the shape:
 *      { success: true, data: [ ...week objects ] }
 * 2. Parse the JSON response.
 * 3. Clear any existing content from the list section.
 * 4. Loop through the data array. For each week object:
 *    - Call createWeekArticle(week).
 *    - Append the returned <article> to the list section.
 */
async function loadWeeks() {
  // ... your implementation here ...
}

// --- Initial Page Load ---
loadWeeks();
