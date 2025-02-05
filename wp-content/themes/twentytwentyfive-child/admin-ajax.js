document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".update-expiry").forEach(function (button) {
        button.addEventListener("click", function () {
            let licenseId = this.getAttribute("data-id");
            let newExpiryDate = document.getElementById("expiry-date-" + licenseId).value;

            let formData = new FormData();
            formData.append("action", "update_license_expiry");
            formData.append("license_id", licenseId);
            formData.append("expiry_date", newExpiryDate);

            fetch(ajaxurl, {
                method: "POST",
                body: formData,
                credentials: "same-origin",
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.success) {
                        alert("✅ " + data.data);
                    } else {
                        alert("❌ " + data.data);
                    }
                })
                .catch((error) => console.error("Error:", error));
        });
    });
});
