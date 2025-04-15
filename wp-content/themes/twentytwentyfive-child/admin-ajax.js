document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".update-expiry").forEach(function (button) {
        button.addEventListener("click", function () {
            let licenseId = this.getAttribute("data-id");
            let newExpiryDate = document.getElementById("expiry-date-" + licenseId).value;

            let formData = new FormData();
            formData.append("action", "update_license_expiry");
            formData.append("license_id", licenseId);
            formData.append("expiry_date", newExpiryDate);

            fetch(licenseAjax.ajaxurl, {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            })
            .then((res) => res.json())
            .then((data) => {
                alert((data.success ? "✅ " : "❌ ") + data.data);
            })
            .catch((err) => {
                console.error("שגיאה ב-AJAX:", err);
                alert("❌ שגיאה כללית");
            });
        });
    });
});
