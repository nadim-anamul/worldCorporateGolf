(function (window) {
  'use strict';

  function basePayload(form) {
    var tshirtSelect = document.getElementById('tshirtSize');
    var customTshirtSize = document.getElementById('customTshirtSize');
    var tshirtVal = tshirtSelect ? tshirtSelect.value : '';
    return {
      csrf_token: form.querySelector('[name="csrf_token"]').value,
      registration_type: document.getElementById('registration_type').value,
      playerCategory: document.getElementById('playerCategory').value,
      referenceName: document.getElementById('referenceName').value.trim(),
      referenceMission: document.getElementById('referenceMission').value.trim(),
      referenceContact: document.getElementById('referenceContact').value.trim(),
      fullName: document.getElementById('nameTitle').value + ' ' + document.getElementById('fullName').value.trim(),
      email: document.getElementById('email').value.trim(),
      contact: document.getElementById('contact').value.trim(),
      nationality: document.getElementById('nationality').value.trim(),
      designation: document.getElementById('designation').value.trim(),
      organization: document.getElementById('organization').value.trim(),
      mailingAddress: document.getElementById('mailingAddress').value.trim(),
      nameOnPolo: document.getElementById('nameOnPolo').value.trim(),
      tshirtSize: tshirtVal === 'Oversize' ? 'Oversize (' + customTshirtSize.value.trim() + ')' : tshirtVal
    };
  }

  function wireCategoryToggle() {
    var categorySelect = document.getElementById('playerCategory');
    var refSection = document.getElementById('referenceSection');
    if (!categorySelect || !refSection) return;
    categorySelect.addEventListener('change', function () {
      var isNonDiplomat = this.value === 'Non-Diplomats';
      refSection.style.display = isNonDiplomat ? 'block' : 'none';
      if (!isNonDiplomat) {
        document.getElementById('referenceName').value = '';
        document.getElementById('referenceMission').value = '';
        document.getElementById('referenceContact').value = '';
      }
    });
  }

  function wireCustomTshirt() {
    var tshirtSelect = document.getElementById('tshirtSize');
    var customTshirtContainer = document.getElementById('customTshirtContainer');
    var customTshirtSize = document.getElementById('customTshirtSize');
    if (!tshirtSelect) return;
    tshirtSelect.addEventListener('change', function () {
      if (this.value === 'Oversize') {
        customTshirtContainer.style.display = 'block';
        customTshirtSize.required = true;
      } else {
        customTshirtContainer.style.display = 'none';
        customTshirtSize.required = false;
        customTshirtSize.value = '';
      }
    });
  }

  function init(options) {
    wireCategoryToggle();
    wireCustomTshirt();

    var form = document.getElementById('regForm');
    var btn = document.getElementById('submitBtn');
    var errorBox = document.getElementById('errorBox');
    var photoInput = document.getElementById('profilePhoto');
    var tshirtSelect = document.getElementById('tshirtSize');
    var customTshirtSize = document.getElementById('customTshirtSize');
    var defaultBtnHtml = btn.innerHTML;

    function showError(msg) {
      errorBox.textContent = msg;
      errorBox.style.display = 'block';
      btn.disabled = false;
      btn.innerHTML = defaultBtnHtml;
      window.scrollTo({ top: errorBox.offsetTop - 20, behavior: 'smooth' });
    }

    function submitFormWithPhoto(photoFile) {
      var scheduleSelected = form.querySelector(options.scheduleSelector);
      var payload = basePayload(form);
      payload.scheduleGroup = scheduleSelected.value;
      if (typeof options.extendPayload === 'function') {
        options.extendPayload(payload);
      }

      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Submitting Registration...';

      var formData = new FormData();
      formData.append('cart_json', JSON.stringify(payload));
      formData.append('profile_photo', photoFile, photoFile.name || 'photo.jpg');

      fetch('payment/initiate.php', { method: 'POST', body: formData })
        .then(function (res) {
          return res.text().then(function (text) {
            try { return JSON.parse(text); }
            catch (e) { throw new Error('Server returned invalid response structure.'); }
          });
        })
        .then(function (data) {
          if (data.status === 'success' && data.payment_page_url) {
            window.location.href = data.payment_page_url;
          } else {
            showError(data.message || 'An error occurred during payment setup. Please retry.');
          }
        })
        .catch(function (err) {
          showError(err.message || 'Connection failure. Please check your internet connection.');
        });
    }

    btn.addEventListener('click', function () {
      errorBox.style.display = 'none';
      if (!form.checkValidity()) {
        form.classList.add('was-validated');
        showError('Please check that all required fields are filled correctly.');
        return;
      }
      if (!form.querySelector(options.scheduleSelector)) {
        showError(options.scheduleError);
        return;
      }
      if (!photoInput || photoInput.files.length === 0) {
        showError('Please upload a profile photo. If you already selected one, choose the file again before submitting.');
        return;
      }
      if (tshirtSelect.value === 'Oversize' && !customTshirtSize.value.trim()) {
        showError('Please enter your custom body width and length sizes.');
        return;
      }

      var file = photoInput.files[0];
      var isHeic = /\.(heic|heif)$/i.test(file.name) || file.type === 'image/heic' || file.type === 'image/heif';
      if (isHeic && window.heic2any) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Converting iPhone Image...';
        heic2any({ blob: file, toType: 'image/jpeg', quality: 0.8 })
          .then(function (convertedBlob) {
            var convertedFile;
            try {
              convertedFile = new File([convertedBlob], file.name.replace(/\.(heic|heif)$/i, '.jpg'), { type: 'image/jpeg' });
            } catch (e) {
              convertedFile = convertedBlob;
            }
            submitFormWithPhoto(convertedFile);
          })
          .catch(function () {
            showError('Failed to process iPhone image. Please try uploading a JPEG or PNG.');
          });
      } else {
        submitFormWithPhoto(file);
      }
    });
  }

  window.RegistrationForm = { init: init };
})(window);
