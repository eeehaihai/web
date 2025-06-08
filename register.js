// 验证用户名
function validateUsername() {
    const username = document.getElementById("username").value;
    const message = document.getElementById("username_message");
    const regex = /^[a-zA-Z0-9_]{5,20}$/;
    
    if (username === "") {
        message.innerHTML = "";
        return false;
    } else if (!regex.test(username)) {
        message.innerHTML = "用户名必须是5-20位字母、数字或下划线";
        return false;
    } else {
        message.innerHTML = "";
        return true;
    }
}

// 验证密码匹配
function checkPasswordMatch() {
    const password = document.getElementById("password").value;
    const confirmPassword = document.getElementById("confirm_password").value;
    const message = document.getElementById("password_match_message");
    
    if (confirmPassword === "") {
        message.innerHTML = "";
        return false;
    } else if (password !== confirmPassword) {
        message.innerHTML = "两次输入的密码不匹配";
        return false;
    } else {
        message.innerHTML = "";
        return true;
    }
}

// 验证电子邮箱
function validateEmail() {
    const email = document.getElementById("email").value;
    const message = document.getElementById("email_message");
    const regex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    
    if (email === "") {
        message.innerHTML = "";
        return false;
    } else if (!regex.test(email)) {
        message.innerHTML = "请输入有效的电子邮箱地址";
        return false;
    } else {
        message.innerHTML = "";
        return true;
    }
}

// 验证手机号码
function validatePhone() {
    const phone = document.getElementById("phone").value;
    const message = document.getElementById("phone_message");
    const regex = /^1[3-9]\d{9}$/;
    
    if (phone === "") {
        message.innerHTML = "";
        return false;
    } else if (!regex.test(phone)) {
        message.innerHTML = "请输入有效的11位手机号码";
        return false;
    } else {
        message.innerHTML = "";
        return true;
    }
}

// 表单提交验证
function validateForm() {
    // 执行所有验证
    const isUsernameValid = validateUsername();
    const isPasswordMatch = checkPasswordMatch();
    const isEmailValid = validateEmail();
    const isPhoneValid = validatePhone();
    
    // 检查"同意条款"复选框
    const agreeTerms = document.getElementById("agree_terms").checked;
    
    // 检查密码长度
    const password = document.getElementById("password").value;
    const isPasswordValid = password.length >= 8;
    
    // 只有当所有验证都通过时才提交表单
    return isUsernameValid && isPasswordMatch && isEmailValid && 
           isPhoneValid && agreeTerms && isPasswordValid;
}

// 初始化页面时为所有字段添加验证
document.addEventListener("DOMContentLoaded", function() {
    // 初始化验证状态
    document.getElementById("username").addEventListener("blur", validateUsername);
    document.getElementById("email").addEventListener("blur", validateEmail);
    document.getElementById("phone").addEventListener("blur", validatePhone);
    document.getElementById("password").addEventListener("blur", checkPasswordMatch); // 密码字段也应触发匹配检查
    document.getElementById("confirm_password").addEventListener("blur", checkPasswordMatch);
});
