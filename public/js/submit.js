// Переключение между фото и видео
document.addEventListener('DOMContentLoaded', function() {
    const mediaTypeBtns = document.querySelectorAll('.media-type-btn');
    const photoGroup = document.getElementById('photoUpload');
    const videoGroup = document.getElementById('videoUpload');
    const photoInput = document.getElementById('photo');
    const videoInput = document.getElementById('video');

    mediaTypeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            // Убираем активный класс со всех кнопок
            mediaTypeBtns.forEach(b => b.classList.remove('active'));
            // Добавляем активный класс нажатой кнопке
            btn.classList.add('active');

            if (btn.dataset.type === 'photo') {
                photoGroup.classList.remove('hidden');
                videoGroup.classList.add('hidden');
                photoInput.required = true;
                videoInput.required = false;
            } else {
                photoGroup.classList.add('hidden');
                videoGroup.classList.remove('hidden');
                photoInput.required = false;
                videoInput.required = true;
            }
        });
    });
});

// Предпросмотр фото
document.getElementById('photoPreview').addEventListener('click', () => {
    document.getElementById('photo').click();
});

document.getElementById('photo').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        // Проверяем размер файла (5MB)
        if (file.size > 5 * 1024 * 1024) {
            showNotification('Файл слишком большой. Максимальный размер: 5MB', 'error');
            this.value = '';
            return;
        }

        // Проверяем тип файла
        if (!file.type.startsWith('image/')) {
            showNotification('Пожалуйста, выберите изображение', 'error');
            this.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('photoPreview');
            preview.style.backgroundImage = `url(${e.target.result})`;
            preview.innerHTML = '';
            preview.classList.add('has-media');
        };
        reader.readAsDataURL(file);
    }
});

// Предпросмотр видео
document.getElementById('videoPreview').addEventListener('click', () => {
    document.getElementById('video').click();
});

document.getElementById('video').addEventListener('change', async function(e) {
    const file = e.target.files[0];
    if (file) {
        // Проверяем размер файла (10MB)
        if (file.size > 10 * 1024 * 1024) {
            showNotification('Видео слишком большое. Максимальный размер: 10MB', 'error');
            this.value = '';
            return;
        }

        // Проверяем тип файла
        if (!file.type.startsWith('video/')) {
            showNotification('Пожалуйста, выберите видео', 'error');
            this.value = '';
            return;
        }

        // Проверяем длительность видео
        const video = document.createElement('video');
        video.preload = 'metadata';
        
        video.onloadedmetadata = function() {
            window.URL.revokeObjectURL(video.src);
            if (video.duration > 15) {
                showNotification('Видео должно быть не длиннее 15 секунд', 'error');
                e.target.value = '';
                return;
            }
            
            const preview = document.getElementById('videoPreview');
            preview.innerHTML = `
                <video controls style="max-width: 100%; max-height: 300px;">
                    <source src="${URL.createObjectURL(file)}" type="${file.type}">
                </video>
            `;
            preview.classList.add('has-media');
        };
        
        video.src = URL.createObjectURL(file);
    }
});

// Отправка формы
document.getElementById('submitForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // Проверяем, что страница открыта через http/https
    if (window.location.protocol === 'file:') {
        showNotification('Пожалуйста, откройте страницу через веб-сервер (http/https)', 'error');
        return;
    }
    
    const formData = new FormData(this);
    const activeType = document.querySelector('.media-type-btn.active').dataset.type;
    
    // Проверяем, выбран ли файл
    const fileInput = activeType === 'photo' ? 
        document.getElementById('photo') : 
        document.getElementById('video');
        
    if (!fileInput.files.length) {
        showNotification('Пожалуйста, выберите файл', 'error');
        return;
    }
    
    // Добавляем тип медиа
    formData.set('type', activeType);
    
    try {
        // Показываем индикатор загрузки
        const submitBtn = this.querySelector('.submit-btn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Отправка...';
        submitBtn.disabled = true;
        
        // Отправляем запрос
        const response = await fetch('api/submit.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        let result;
        try {
            result = await response.json();
        } catch (parseError) {
            throw new Error('Не удалось обработать ответ сервера. Попробуйте еще раз.');
        }
        
        if (!response.ok) {
            throw new Error(result.error || 'Ошибка сервера. Пожалуйста, попробуйте позже.');
        }
        
        if (result.success) {
            showNotification('Заявка успешно отправлена!');
            // Очищаем форму
            this.reset();
            // Очищаем превью
            document.getElementById('photoPreview').innerHTML = '<i class="fas fa-cloud-upload-alt"></i><span>Нажмите или перетащите фото</span>';
            document.getElementById('videoPreview').innerHTML = '<i class="fas fa-cloud-upload-alt"></i><span>Нажмите или перетащите видео</span>';
            // Перенаправляем на главную через 2 секунды
            setTimeout(() => {
                window.location.href = './';
            }, 2000);
        } else {
            throw new Error(result.error || 'Произошла ошибка при отправке. Попробуйте еще раз.');
        }
    } catch (error) {
        // Логируем ошибку для отладки
        if (error.stack) {
            console.error('Подробности ошибки:', {
                message: error.message,
                stack: error.stack
            });
        } else {
            console.error('Ошибка:', error.message || error);
        }
        
        // Показываем пользователю понятное сообщение
        showNotification(
            error.message || 'Произошла неизвестная ошибка. Пожалуйста, попробуйте позже.',
            'error'
        );
    } finally {
        // Восстанавливаем кнопку
        const submitBtn = this.querySelector('.submit-btn');
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Отправить';
        submitBtn.disabled = false;
    }
});

// Показ уведомлений
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = 'notification ' + type;
    
    const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
    notification.innerHTML = `
        <i class="fas fa-${icon}"></i>
        ${message}
    `;
    
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideIn 0.3s ease-out reverse';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
} 