// 检查用户是否已登录
function isLoggedIn() {
    return sessionStorage.getItem('login') !== null;
}

// 获取当前登录的用户名
function getCurrentUser() {
    return sessionStorage.getItem('login');
}

// 退出登录
function logout() {
    // 发送请求到服务器删除会话
    sendAjaxRequest('GET', 'logout.php', null, 
        function(response) {
            // 无论服务器响应如何，都清除本地会话存储并跳转
            sessionStorage.removeItem('login');
            window.location.href = 'home.html'; // 或 login.html
        },
        function() {
            // 网络错误等也应清除本地状态并跳转
            sessionStorage.removeItem('login');
            window.location.href = 'home.html'; // 或 login.html
        }
    );
}

// 获取所有帖子
function getAllPosts(callback) {
    sendAjaxRequest('GET', 'topics.php', null, function(response) {
        if (response && response.success) {
            callback(response.topics);
        } else {
            console.error('获取帖子失败:', response ? response.message : '未知错误');
            callback([]); // 返回空数组以避免后续错误
        }
    }, function() {
        console.error('获取帖子请求失败');
        callback([]); // 网络错误等也返回空数组
    });
}

// 发送AJAX请求的通用函数
function sendAjaxRequest(method, url, data, successCallback, errorCallback) {
    const xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    
    // 如果是POST请求且数据不是FormData，则设置Content-Type
    // FormData会自动设置正确的Content-Type (multipart/form-data)
    if (method === 'POST' && !(data instanceof FormData)) {
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    }
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (successCallback) successCallback(response);
            } catch (e) {
                console.error('解析响应数据出错:', e, xhr.responseText);
                if (errorCallback) errorCallback({ message: '响应数据格式错误' });
            }
        } else {
            console.error('HTTP错误:', xhr.status, xhr.statusText);
            if (errorCallback) errorCallback({ message: `请求失败，状态码: ${xhr.status}` });
        }
    };
    
    xhr.onerror = function() {
        console.error('网络错误');
        if (errorCallback) errorCallback({ message: '网络连接错误' });
    };
    
    if (data instanceof FormData) {
        xhr.send(data);
    } else if (data && typeof data === 'object') {
        // 将对象转换为URL编码格式的字符串
        let params = Object.keys(data).map(key => 
            encodeURIComponent(key) + '=' + encodeURIComponent(data[key])
        ).join('&');
        xhr.send(params);
    } else {
        xhr.send(data); // 发送null或字符串等
    }
}

// 更新导航栏用户状态
function updateNavUserStatus() {
    const userActions = document.querySelector('.user-actions');
    if (!userActions) return;
    
    // 发送请求到服务器检查会话状态
    sendAjaxRequest('GET', 'check_session.php', null, function(response) {
        if (response.loggedIn) {
            // 已登录状态
            sessionStorage.setItem('login', response.username); // 同步前端sessionStorage
            userActions.innerHTML = `
                <span class="welcome-text">欢迎, ${safeHtml(response.username)}</span>
                <a href="#" onclick="logout()" class="logout-btn">退出登录</a>
            `;
        } else {
            // 未登录状态
            sessionStorage.removeItem('login'); // 清除前端sessionStorage
            userActions.innerHTML = `
                <a href="login.html" class="login-btn">登录</a>
                <a href="register.html" class="register-btn">注册</a>
            `;
        }
    }, function() {
        // 如果 check_session.php 请求失败，回退到本地存储检查 (可能不准确)
        console.warn('检查会话请求失败，回退到本地检查。');
        if (isLoggedIn()) {
            userActions.innerHTML = `
                <span class="welcome-text">欢迎, ${safeHtml(getCurrentUser())}</span>
                <a href="#" onclick="logout()" class="logout-btn">退出登录</a>
            `;
        } else {
            userActions.innerHTML = `
                <a href="login.html" class="login-btn">登录</a>
                <a href="register.html" class="register-btn">注册</a>
            `;
        }
    });
}

// 检查登录状态，如果需要登录但未登录，则重定向到登录页面
function checkLoginRequired() {
    if (!isLoggedIn()) {
        alert('请先登录');
        window.location.href = 'login.html';
        return false;
    }
    return true;
}

// 格式化时间戳为可读形式 (YYYY-MM-DD HH:MM)
function formatTime(timestamp) {
    const date = new Date(parseInt(timestamp, 10)); // 确保是数字
    return `${date.getFullYear()}-${padZero(date.getMonth() + 1)}-${padZero(date.getDate())} ${padZero(date.getHours())}:${padZero(date.getMinutes())}`;
}

// 数字补零
function padZero(num) {
    return num < 10 ? '0' + num : num;
}

// 安全处理HTML内容，防止XSS
function safeHtml(content) {
    if (typeof content !== 'string') {
        content = String(content); // 转换为字符串
    }
    const tempDiv = document.createElement('div');
    tempDiv.textContent = content;
    return tempDiv.innerHTML;
}

// 截断长文本，末尾加省略号
function truncateText(text, maxLength = 150) { // 默认长度调整为150
    if (typeof text !== 'string') text = String(text);
    if (text.length <= maxLength) return text;
    
    // 尝试在接近最大长度的最后一个空格处截断，以保持单词完整性
    let cutPoint = text.substring(0, maxLength).lastIndexOf(' ');
    // 如果找不到空格或者空格位置太靠前，则直接在maxLength处截断
    if (cutPoint === -1 || cutPoint < maxLength / 2) {
        cutPoint = maxLength;
    }
    
    return text.substring(0, cutPoint) + '...';
}

// 显示消息提示
function showMessage(elementId, message, type = 'error') {
    const messageEl = document.getElementById(elementId);
    if (!messageEl) {
        console.error(`消息元素 #${elementId} 未找到`);
        return;
    }
    
    messageEl.innerHTML = safeHtml(message); // 确保消息内容安全
    messageEl.className = `message-box ${type}`; // type可以是 'success', 'error', 'info'
    messageEl.style.display = 'block';
    
    // 如果是成功消息，并且不是永久性的，可以设置超时自动隐藏
    if (type === 'success') {
        setTimeout(() => {
            messageEl.style.display = 'none';
        }, 3000); // 3秒后隐藏
    }
    
    return messageEl;
}

// 表单提交的通用处理函数
function handleFormSubmit(formElement, url, onSuccess, onError) {
    if (!formElement) {
        console.error('表单元素未提供给 handleFormSubmit');
        return;
    }
    
    formElement.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // 创建FormData对象
        const formData = new FormData(formElement);
        
        // 发送请求
        sendAjaxRequest('POST', url, formData,
            function(response) { // 成功回调
                if (response.success) {
                    if (onSuccess) onSuccess(response);
                } else {
                    if (onError) onError(response.message || '操作失败，请稍后再试');
                }
            },
            function(error) { // 错误回调
                if (onError) onError(error.message || '网络错误，请检查您的连接');
            }
        );
    });
}

// 处理图片预览的通用函数
function setupImagePreview(inputId, previewContainerId, maxImages = 3, countElementId = null) {
    const input = document.getElementById(inputId);
    const previewContainer = document.getElementById(previewContainerId);
    const countEl = countElementId ? document.getElementById(countElementId) : null;
    
    if (!input || !previewContainer) {
        console.error(`图片预览所需的元素 #${inputId} 或 #${previewContainerId} 未找到`);
        return;
    }
    
    input.addEventListener('change', function(e) {
        const files = e.target.files;
        const fileCount = files.length;
        
        if (countEl) {
            countEl.textContent = fileCount > 0 ? `已选择 ${fileCount} 个文件` : '未选择文件';
        }
        
        // 清空预览容器
        previewContainer.innerHTML = '';
        
        // 限制预览数量
        const numToPreview = Math.min(fileCount, maxImages);
        
        for(let i = 0; i < numToPreview; i++) {
            const file = files[i];
            if(file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const img = document.createElement('img');
                    img.src = event.target.result;
                    // 可以在CSS中统一样式，而不是在这里设置
                    // img.style.width = '80px';
                    // img.style.height = '80px';
                    // img.style.objectFit = 'cover';
                    // img.style.borderRadius = '4px';
                    // img.style.margin = '5px';
                    previewContainer.appendChild(img);
                }
                reader.readAsDataURL(file);
            }
        }
        if (fileCount > maxImages) {
            const notice = document.createElement('p');
            notice.textContent = `注意：最多只显示和上传 ${maxImages} 张图片。`;
            notice.style.fontSize = '0.9em';
            notice.style.color = 'orange';
            previewContainer.appendChild(notice);
        }
    });
}
