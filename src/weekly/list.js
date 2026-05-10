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

loadWeeks();
