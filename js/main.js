// Данные участниц (в реальном приложении это будет загружаться из JSON файла)
let contestants = [
    { id: 1, image: 'images/placeholder.jpg', rating: 1500 },
    // Здесь будут другие участницы
];

let currentPair = null;
let totalVotes = 0;
let currentCategory = 'female';
let lastVote = null;
let votedContestant = null;
let roundsCompleted = 0;
const totalRounds = 10;
let requiredVotes = 50; // Значение по умолчанию
let isFinalRound = false;
let hasVoted = false;

// Загрузка данных при старте
document.addEventListener('DOMContentLoaded', async () => {
    // Проверяем, голосовал ли уже пользователь
    hasVoted = localStorage.getItem('hasVoted') === 'true';
    
    // Загружаем настройки голосования
    await loadVotingSettings();
    
    // Проверяем авторизацию
    if (!await checkAuth()) {
        window.location.href = 'auth.html';
        return;
    }

    await loadContestants();
    loadFromLocalStorage();
    setupCategorySwitch();
    nextBattle();
    
    // Закрытие модального окна при клике на крестик
    document.querySelector('.close').addEventListener('click', () => {
        document.getElementById('leaderboard-modal').style.display = 'none';
    });
    
    // Закрытие модального окна при клике вне его
    window.addEventListener('click', (e) => {
        const modal = document.getElementById('leaderboard-modal');
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });

    loadNewPair();
    updateRoundProgress();
    loadTotalVotes();
});

// Настройка переключателя категорий
function setupCategorySwitch() {
    const categorySwitch = document.createElement('div');
    categorySwitch.className = 'category-switch';
    categorySwitch.innerHTML = `
        <button onclick="switchCategory('female')" class="category-button ${currentCategory === 'female' ? 'active' : ''}">
            <i class="fas fa-venus"></i> Девушки
        </button>
        <button onclick="switchCategory('male')" class="category-button ${currentCategory === 'male' ? 'active' : ''}">
            <i class="fas fa-mars"></i> Парни
        </button>
    `;
    document.querySelector('.stats').appendChild(categorySwitch);
}

// Переключение категории
function switchCategory(category) {
    currentCategory = category;
    
    if (category === 'male') {
        document.querySelector('.battle-container').innerHTML = `
            <div class="final-results">
                <h2>Скоро будет</h2>
                <p style="text-align: center; font-size: 1.2rem; color: #666; margin-top: 1rem;">
                    <i class="fas fa-clock"></i> Раздел находится в разработке
                </p>
            </div>
        `;
        document.querySelector('.battle-controls').style.display = 'none';
    } else {
        loadContestants();
        nextBattle();
        document.querySelector('.battle-controls').style.display = 'flex';
    }
    
    document.querySelectorAll('.category-button').forEach(btn => {
        btn.classList.toggle('active', btn.onclick.toString().includes(category));
    });
}

// Загрузка данных из localStorage
function loadFromLocalStorage() {
    const savedContestants = localStorage.getItem('contestants');
    const savedVotes = localStorage.getItem('totalVotes');
    const savedCategory = localStorage.getItem('currentCategory');
    
    if (savedContestants) {
        contestants = JSON.parse(savedContestants);
    }
    
    if (savedVotes) {
        totalVotes = parseInt(savedVotes);
        document.getElementById('total-votes').textContent = totalVotes;
    }

    if (savedCategory) {
        currentCategory = savedCategory;
    }
}

// Система кэширования
const cache = {
    data: new Map(),
    timeouts: new Map(),
    
    set(key, value, timeout = 300000) { // 5 минут по умолчанию
        this.data.set(key, value);
        
        // Очищаем предыдущий таймаут, если есть
        if (this.timeouts.has(key)) {
            clearTimeout(this.timeouts.get(key));
        }
        
        // Устанавливаем новый таймаут
        const timeoutId = setTimeout(() => {
            this.data.delete(key);
            this.timeouts.delete(key);
        }, timeout);
        
        this.timeouts.set(key, timeoutId);
    },
    
    get(key) {
        return this.data.get(key);
    },
    
    has(key) {
        return this.data.has(key);
    },
    
    clear() {
        this.data.clear();
        this.timeouts.forEach(clearTimeout);
        this.timeouts.clear();
    }
};

// Обновляем функцию загрузки участников с кэшированием
async function loadContestants() {
    const cacheKey = `contestants_${currentCategory}`;
    
    if (cache.has(cacheKey)) {
        return cache.get(cacheKey);
    }
    
    try {
        const response = await fetch('/api/contestants/current-pair', {
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            cache.set(cacheKey, data);
            return data;
        }
    } catch (error) {
        console.error('Error loading contestants:', error);
        throw error;
    }
}

// Загрузка новой пары участников
async function loadNewPair() {
    try {
        const response = await fetch('/api/get-pair.php');
        const data = await response.json();
        
        if (data.success) {
            currentPair = data.pair;
            updateContestants(data.pair);
            votedContestant = null;
            document.querySelector('.cancel-vote').style.display = 'none';
        } else {
            showError('Не удалось загрузить пару участников');
        }
    } catch (error) {
        console.error('Ошибка при загрузке пары:', error);
        showError('Произошла ошибка при загрузке участников');
    }
}

// Обновление отображения участников
function updateContestants(pair) {
    const [contestant1, contestant2] = pair;
    
    // Обновляем первого участника
    document.getElementById('contestant1').src = contestant1.photo;
    document.getElementById('name1').textContent = contestant1.name;
    document.getElementById('rating1').textContent = contestant1.rating;
    
    // Обновляем второго участника
    document.getElementById('contestant2').src = contestant2.photo;
    document.getElementById('name2').textContent = contestant2.name;
    document.getElementById('rating2').textContent = contestant2.rating;
    
    // Сбрасываем состояние кнопок
    document.querySelectorAll('.vote-button').forEach(button => {
        button.classList.remove('voted', 'disabled');
    });
}

// Загрузка настроек голосования
async function loadVotingSettings() {
    try {
        const response = await fetch('/api/voting-settings.php');
        const data = await response.json();
        
        if (data.success) {
            requiredVotes = data.requiredVotes;
            isFinalRound = data.isFinalRound;
        }
    } catch (error) {
        console.error('Ошибка при загрузке настроек:', error);
    }
}

// Голосование за участника
async function vote(contestantNumber) {
    if (!currentPair || votedContestant !== null || hasVoted) {
        showError('Вы уже проголосовали');
        return;
    }
    
    try {
        const contestant = currentPair[contestantNumber - 1];
        const response = await fetch('/api/vote.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                contestant_id: contestant.id,
                is_final: isFinalRound
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            votedContestant = contestantNumber;
            updateVoteUI(contestantNumber);
            updateTotalVotes(data.total_votes);
            
            // В обычном режиме отмечаем, что пользователь проголосовал
            if (!isFinalRound) {
                hasVoted = true;
                localStorage.setItem('hasVoted', 'true');
            }
            
            // Проверяем, достиг ли участник необходимого количества голосов
            if (data.contestant_votes >= requiredVotes && !isFinalRound) {
                showWinnerMessage(contestant);
            }
            
            // В финальном раунде проверяем время окончания
            if (isFinalRound && data.voting_ended) {
                showFinalResults();
            }
        } else {
            showError(data.error || 'Ошибка при голосовании');
        }
    } catch (error) {
        console.error('Ошибка при голосовании:', error);
        showError('Произошла ошибка при отправке голоса');
    }
}

// Обновление интерфейса после голосования
function updateVoteUI(votedNumber) {
    const buttons = document.querySelectorAll('.vote-button');
    
    buttons.forEach((button, index) => {
        if (index + 1 === votedNumber) {
            button.classList.add('voted');
            button.innerHTML = '<i class="fas fa-check"></i> Выбрано';
        } else {
            button.classList.add('disabled');
        }
    });
    
    document.querySelector('.cancel-vote').style.display = 'block';
}

// Отмена голоса
async function cancelVote() {
    if (votedContestant === null) return;
    
    try {
        const response = await fetch('/api/cancel-vote.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                contestant_id: currentPair[votedContestant - 1].id
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            votedContestant = null;
            roundsCompleted--;
            updateRoundProgress();
            updateTotalVotes(data.total_votes);
            
            // Сбрасываем UI
            document.querySelectorAll('.vote-button').forEach(button => {
                button.classList.remove('voted', 'disabled');
                button.innerHTML = '<i class="fas fa-heart"></i> Голосовать';
            });
            
            document.querySelector('.cancel-vote').style.display = 'none';
        } else {
            showError(data.error || 'Не удалось отменить голос');
        }
    } catch (error) {
        console.error('Ошибка при отмене голоса:', error);
        showError('Произошла ошибка при отмене голоса');
    }
}

// Загрузка следующей пары
function nextBattle() {
    if (roundsCompleted >= totalRounds) {
        showCompletionMessage();
    } else {
        loadNewPair();
    }
}

// Обновление прогресса раундов
function updateRoundProgress() {
    const progressBar = document.getElementById('roundProgress');
    const currentRoundSpan = document.getElementById('currentRound');
    const roundInfo = document.querySelector('.round-info span');
    
    if (isFinalRound) {
        roundInfo.innerHTML = '<i class="fas fa-star"></i> Финальное голосование';
        progressBar.style.width = '100%';
    } else {
        const progress = (roundsCompleted / 10) * 100;
        progressBar.style.width = `${progress}%`;
        currentRoundSpan.textContent = roundsCompleted + 1;
    }
}

// Показ сообщения о завершении голосования
function showCompletionMessage() {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-trophy"></i> Голосование завершено!</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <p>Спасибо за участие в голосовании!</p>
                <p>Вы можете посмотреть текущий рейтинг участников или начать новую серию голосований.</p>
                <div class="modal-buttons">
                    <button onclick="showLeaderboard()" class="auth-button">
                        <i class="fas fa-list"></i> Посмотреть рейтинг
                    </button>
                    <button onclick="resetRounds()" class="auth-button secondary">
                        <i class="fas fa-redo"></i> Начать заново
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    modal.querySelector('.close').onclick = () => {
        modal.remove();
    };
}

// Сброс раундов
function resetRounds() {
    roundsCompleted = 0;
    updateRoundProgress();
    loadNewPair();
    document.querySelector('.modal').remove();
}

// Показ лидерборда
async function showLeaderboard() {
    const modal = document.getElementById('leaderboard-modal');
    const list = document.getElementById('leaderboard-list');
    
    try {
        const response = await fetch('/api/leaderboard.php');
        const data = await response.json();
        
        if (data.success) {
            list.innerHTML = data.leaders.map((leader, index) => `
                <div class="leaderboard-item">
                    <div class="rank">${index + 1}</div>
                    <img src="${leader.photo}" alt="${leader.name}">
                    <div class="contestant-info">
                        <h3>${leader.name}</h3>
                        <div class="rating">
                            <i class="fas fa-star"></i> ${leader.rating}
                        </div>
                    </div>
                </div>
            `).join('');
            
            modal.style.display = 'block';
        } else {
            showError('Не удалось загрузить таблицу лидеров');
        }
    } catch (error) {
        console.error('Ошибка при загрузке лидерборда:', error);
        showError('Произошла ошибка при загрузке таблицы лидеров');
    }
}

// Загрузка общего количества голосов
async function loadTotalVotes() {
    try {
        const response = await fetch('/api/total-votes.php');
        const data = await response.json();
        
        if (data.success) {
            updateTotalVotes(data.total_votes);
        }
    } catch (error) {
        console.error('Ошибка при загрузке общего количества голосов:', error);
    }
}

// Обновление отображения общего количества голосов
function updateTotalVotes(total) {
    document.getElementById('total-votes').textContent = total;
}

// Показ сообщения об ошибке
function showError(message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.innerHTML = `
        <i class="fas fa-exclamation-circle"></i>
        ${message}
    `;
    
    document.querySelector('main').insertBefore(errorDiv, document.querySelector('main').firstChild);
    
    setTimeout(() => {
        errorDiv.remove();
    }, 5000);
}

// Обработчики закрытия модальных окон
document.querySelectorAll('.modal .close').forEach(closeButton => {
    closeButton.onclick = function() {
        this.closest('.modal').style.display = 'none';
    };
});

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
};

// Показ сообщения о победителе
function showWinnerMessage(contestant) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-trophy"></i> Участник проходит дальше!</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="winner-info">
                    <img src="${contestant.photo}" alt="${contestant.name}">
                    <h3>${contestant.name}</h3>
                    <p>Набрано голосов: ${contestant.votes}</p>
                </div>
                <p>Поздравляем! Участник набрал необходимое количество голосов и проходит в следующий этап!</p>
                <button onclick="loadNewPair()" class="auth-button">
                    <i class="fas fa-forward"></i> Следующая пара
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    modal.querySelector('.close').onclick = () => {
        modal.remove();
        loadNewPair();
    };
}

// Показ финальных результатов
async function showFinalResults() {
    try {
        const response = await fetch('/api/final-results.php');
        const data = await response.json();
        
        if (data.success) {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><i class="fas fa-crown"></i> Победитель определен!</h2>
                        <span class="close">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div class="winner-info">
                            <img src="${data.winner.photo}" alt="${data.winner.name}">
                            <h3>${data.winner.name}</h3>
                            <p>Финальный рейтинг: ${data.winner.rating}</p>
                            <p>Всего голосов: ${data.winner.votes}</p>
                        </div>
                        <div class="final-stats">
                            <h4>Статистика финала:</h4>
                            <p>Длительность: ${data.duration}</p>
                            <p>Всего проголосовало: ${data.total_voters}</p>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            modal.querySelector('.close').onclick = () => {
                modal.remove();
                window.location.reload();
            };
        }
    } catch (error) {
        console.error('Ошибка при загрузке финальных результатов:', error);
        showError('Не удалось загрузить результаты');
    }
}

// Проверка авторизации
async function checkAuth() {
    const token = localStorage.getItem('auth_token');
    if (!token) {
        return false;
    }

    try {
        const response = await fetch('/api/auth/verify-token', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        return response.ok;
    } catch (error) {
        console.error('Error checking authentication:', error);
        return false;
    }
}

// Система уведомлений
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        ${message}
    `;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideInRight 0.3s ease-out reverse';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Ленивая загрузка изображений
function lazyLoadImages() {
    const images = document.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.add('loaded');
                observer.unobserve(img);
            }
        });
    });

    images.forEach(img => imageObserver.observe(img));
}

// Тёмная тема
function initThemeSwitch() {
    const themeSwitch = document.createElement('button');
    themeSwitch.className = 'theme-switch';
    themeSwitch.innerHTML = '<i class="fas fa-moon"></i>';
    document.body.appendChild(themeSwitch);

    const theme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', theme);
    themeSwitch.innerHTML = `<i class="fas fa-${theme === 'light' ? 'moon' : 'sun'}"></i>`;

    themeSwitch.addEventListener('click', () => {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        themeSwitch.innerHTML = `<i class="fas fa-${newTheme === 'light' ? 'moon' : 'sun'}"></i>`;
    });
}

// Система достижений
const achievements = {
    first_vote: {
        id: 'first_vote',
        title: 'Первый голос',
        description: 'Вы проголосовали в первый раз',
        icon: 'star'
    },
    ten_votes: {
        id: 'ten_votes',
        title: 'Активный избиратель',
        description: 'Вы проголосовали 10 раз',
        icon: 'award'
    },
    winner_found: {
        id: 'winner_found',
        title: 'Нашли победителя',
        description: 'Один из участников набрал необходимое количество голосов',
        icon: 'trophy'
    }
};

function unlockAchievement(achievementId) {
    const achievement = achievements[achievementId];
    if (!achievement) return;

    const unlockedAchievements = JSON.parse(localStorage.getItem('achievements') || '[]');
    if (unlockedAchievements.includes(achievementId)) return;

    unlockedAchievements.push(achievementId);
    localStorage.setItem('achievements', JSON.stringify(unlockedAchievements));

    const achievementElement = document.createElement('div');
    achievementElement.className = 'achievement';
    achievementElement.innerHTML = `
        <div class="achievement-icon">
            <i class="fas fa-${achievement.icon}"></i>
        </div>
        <div class="achievement-info">
            <h3>${achievement.title}</h3>
            <p>${achievement.description}</p>
        </div>
    `;
    document.body.appendChild(achievementElement);

    setTimeout(() => {
        achievementElement.style.animation = 'fadeOut 0.5s ease-out';
        setTimeout(() => achievementElement.remove(), 500);
    }, 3000);
}

// Подсказки для новых пользователей
function initTutorial() {
    const hasSeenTutorial = localStorage.getItem('has_seen_tutorial');
    if (hasSeenTutorial) return;

    const tutorialSteps = [
        {
            element: '.battle-card',
            message: 'Выберите участника, который вам нравится больше'
        },
        {
            element: '.vote-button',
            message: 'Нажмите здесь, чтобы проголосовать'
        },
        {
            element: '.next-battle',
            message: 'Переходите к следующей паре участников'
        }
    ];

    let currentStep = 0;
    showTutorialStep();

    function showTutorialStep() {
        if (currentStep >= tutorialSteps.length) {
            localStorage.setItem('has_seen_tutorial', 'true');
            return;
        }

        const step = tutorialSteps[currentStep];
        const element = document.querySelector(step.element);
        if (!element) return;

        element.setAttribute('data-tooltip', step.message);
        element.classList.add('tooltip');

        element.addEventListener('click', () => {
            element.classList.remove('tooltip');
            currentStep++;
            showTutorialStep();
        }, { once: true });
    }
}

// Эффекты конфетти при победе
function showConfetti() {
    const colors = ['#ffd700', '#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4'];
    const confettiCount = 100;

    for (let i = 0; i < confettiCount; i++) {
        const confetti = document.createElement('div');
        confetti.className = 'confetti';
        confetti.style.cssText = `
            position: fixed;
            width: 10px;
            height: 10px;
            background: ${colors[Math.floor(Math.random() * colors.length)]};
            left: ${Math.random() * 100}vw;
            top: -10px;
            animation: confetti ${1 + Math.random() * 2}s linear forwards;
        `;
        document.body.appendChild(confetti);
        setTimeout(() => confetti.remove(), 3000);
    }
}

// Инициализация всех новых функций
document.addEventListener('DOMContentLoaded', () => {
    initThemeSwitch();
    lazyLoadImages();
    initTutorial();
});

// Обновляем существующие функции для поддержки новых возможностей
const originalVote = vote;
vote = async function(contestantNumber) {
    const result = await originalVote(contestantNumber);
    
    if (result.success) {
        const unlockedAchievements = JSON.parse(localStorage.getItem('achievements') || '[]');
        if (!unlockedAchievements.includes('first_vote')) {
            unlockAchievement('first_vote');
        }
        
        const totalVotes = parseInt(localStorage.getItem('total_votes') || '0') + 1;
        localStorage.setItem('total_votes', totalVotes);
        
        if (totalVotes === 10) {
            unlockAchievement('ten_votes');
        }
        
        if (result.contestant_votes >= result.required_votes) {
            unlockAchievement('winner_found');
            showConfetti();
        }
        
        showNotification('Ваш голос учтен!');
    }
    
    return result;
};

// Функции для работы с комментариями
let currentPage = 1;
const commentsPerPage = 10;

// Обновляем функцию загрузки комментариев с кэшированием
async function loadComments(contestantId, page = 1) {
    const cacheKey = `comments_${contestantId}_${page}`;
    
    if (cache.has(cacheKey)) {
        return cache.get(cacheKey);
    }
    
    try {
        const response = await fetch(`/api/comments.php?contestant_id=${contestantId}&page=${page}`);
        const data = await response.json();
        
        if (data.success) {
            cache.set(cacheKey, data, 60000); // Кэшируем на 1 минуту
            return data;
        }
    } catch (error) {
        console.error('Error loading comments:', error);
        throw error;
    }
}

function createCommentElement(comment) {
    const div = document.createElement('div');
    div.className = 'comment';
    div.innerHTML = `
        <div class="comment-header">
            <span class="comment-author">@${comment.author}</span>
            <span class="comment-date">${formatDate(comment.created_at)}</span>
        </div>
        <div class="comment-text">${escapeHtml(comment.comment)}</div>
        <div class="comment-actions">
            <button class="like-button ${comment.is_liked ? 'liked' : ''}" onclick="likeComment(${comment.id})">
                <i class="fas fa-heart"></i>
                <span class="likes-count">${comment.likes}</span>
            </button>
        </div>
    `;
    return div;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) { // меньше минуты
        return 'только что';
    } else if (diff < 3600000) { // меньше часа
        const minutes = Math.floor(diff / 60000);
        return `${minutes} ${declOfNum(minutes, ['минуту', 'минуты', 'минут'])} назад`;
    } else if (diff < 86400000) { // меньше суток
        const hours = Math.floor(diff / 3600000);
        return `${hours} ${declOfNum(hours, ['час', 'часа', 'часов'])} назад`;
    } else {
        return date.toLocaleDateString();
    }
}

function declOfNum(n, titles) {
    return titles[(n % 10 === 1 && n % 100 !== 11) ? 0 : n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20) ? 1 : 2];
}

function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function toggleComments(contestantId) {
    const commentsSection = document.getElementById(`comments-${contestantId}`);
    const isActive = commentsSection.classList.contains('active');
    
    // Закрываем все открытые секции комментариев
    document.querySelectorAll('.comments-section').forEach(section => {
        section.classList.remove('active');
    });
    
    if (!isActive) {
        commentsSection.classList.add('active');
        loadComments(contestantId);
    }
}

async function submitComment(event, contestantId) {
    event.preventDefault();
    
    const form = event.target;
    const textarea = form.querySelector('textarea');
    const comment = textarea.value.trim();
    
    if (!comment) return;
    
    try {
        const response = await fetch('/api/comments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
            },
            body: JSON.stringify({
                contestant_id: contestantId,
                comment: comment
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            textarea.value = '';
            loadComments(contestantId); // Перезагружаем комментарии
            showNotification('Комментарий добавлен');
            
            // Проверяем достижение
            const commentCount = parseInt(localStorage.getItem('comment_count') || '0') + 1;
            localStorage.setItem('comment_count', commentCount);
            
            if (commentCount === 1) {
                unlockAchievement('first_comment');
            } else if (commentCount === 10) {
                unlockAchievement('active_commenter');
            }
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        console.error('Error submitting comment:', error);
        showNotification('Ошибка при отправке комментария', 'error');
    }
}

async function likeComment(commentId) {
    try {
        const response = await fetch(`/api/comments.php?action=like`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
            },
            body: JSON.stringify({
                comment_id: commentId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const likeButton = document.querySelector(`[onclick="likeComment(${commentId})"]`);
            const likesCount = likeButton.querySelector('.likes-count');
            
            likeButton.classList.toggle('liked', data.action === 'liked');
            likesCount.textContent = data.likes;
            
            // Проверяем достижение
            if (data.action === 'liked') {
                const likeCount = parseInt(localStorage.getItem('like_count') || '0') + 1;
                localStorage.setItem('like_count', likeCount);
                
                if (likeCount === 10) {
                    unlockAchievement('like_master');
                }
            }
        }
    } catch (error) {
        console.error('Error liking comment:', error);
        showNotification('Ошибка при выполнении действия', 'error');
    }
}

// Добавляем новые достижения для комментариев
Object.assign(achievements, {
    first_comment: {
        id: 'first_comment',
        title: 'Первый комментарий',
        description: 'Вы оставили свой первый комментарий',
        icon: 'comment'
    },
    active_commenter: {
        id: 'active_commenter',
        title: 'Активный комментатор',
        description: 'Вы оставили 10 комментариев',
        icon: 'comments'
    },
    like_master: {
        id: 'like_master',
        title: 'Мастер лайков',
        description: 'Вы поставили 10 лайков',
        icon: 'heart'
    }
}); 