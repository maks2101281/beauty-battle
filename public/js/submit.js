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
    
    const formData = new FormData();
    const activeType = document.querySelector('.media-type-btn.active').dataset.type;
    
    formData.append('name', document.getElementById('name').value);
    formData.append('type', activeType);
    formData.append('social', document.getElementById('social').value);
    
    // Добавляем файл в зависимости от типа
    const file = activeType === 'photo' ? 
        document.getElementById('photo').files[0] : 
        document.getElementById('video').files[0];
    
    if (!file) {
        showNotification('Пожалуйста, выберите файл', 'error');
        return;
    }
    
    formData.append('media', file);
    
    try {
        const baseUrl = window.location.hostname === 'localhost' ? 
            'http://localhost:8000' : 
            window.location.origin;
            
        const response = await fetch(`${baseUrl}/api/submit.php`, {
            method: 'POST',
            body: formData,
            mode: 'cors',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Заявка успешно отправлена!');
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 2000);
        } else {
            throw new Error(result.error || 'Произошла ошибка при отправке');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification(error.message, 'error');
    }
});

// Показ уведомлений
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        ${message}
    `;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideIn 0.3s ease-out reverse';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
} 