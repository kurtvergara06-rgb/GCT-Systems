document.addEventListener("DOMContentLoaded", function () {
  const loginForm = document.getElementById("loginForm");
  const loginEmail = document.getElementById("loginEmail");
  const loginPassword = document.getElementById("loginPassword");
  const loginMessage = document.getElementById("loginMessage");
  const passwordIcon = document.getElementById("passwordIcon");

  passwordIcon.addEventListener("click", function () {
    if (loginPassword.type === "password") {
      loginPassword.type = "text";
      passwordIcon.classList.remove("fa-eye");
      passwordIcon.classList.add("fa-eye-slash");
    } else {
      loginPassword.type = "password";
      passwordIcon.classList.remove("fa-eye-slash");
      passwordIcon.classList.add("fa-eye");
    }
  });

  loginForm.addEventListener("submit", function (e) {
    e.preventDefault();

    const email = loginEmail.value.trim();
    const password = loginPassword.value.trim();

    loginMessage.className = "";
    loginMessage.textContent = "";

    if (email === "" || password === "") {
      loginMessage.textContent = "Please fill in all fields.";
      loginMessage.classList.add("error");
      return;
    }

    loginMessage.textContent = "Login form is working.";
    loginMessage.classList.add("success");

    /*
      Later, when you already have database login,
      this is where Laravel authentication will be connected.
    */
  });
});