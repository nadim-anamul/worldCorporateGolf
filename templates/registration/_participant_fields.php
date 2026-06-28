      <div class="form-section-card">
        <h6 class="form-section-title"><i class="bi bi-card-text text-gold"></i> Personal &amp; Contact Details</h6>
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="fullName" class="form-label">Full Name <span class="text-danger">*</span></label>
            <div class="input-group">
              <select class="form-select" id="nameTitle" style="max-width: 90px;" required>
                <option value="Mr." selected>Mr.</option>
                <option value="Mrs.">Mrs.</option>
                <option value="Ms.">Ms.</option>
                <option value="Dr.">Dr.</option>
                <option value="Prof.">Prof.</option>
              </select>
              <input type="text" class="form-control" id="fullName" required placeholder="Name on certificate" />
            </div>
          </div>
          <div class="col-md-6">
            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
            <input type="email" class="form-control" id="email" required placeholder="name@domain.com" />
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="contact" class="form-label">Contact Mobile <span class="text-danger">*</span></label>
            <input type="tel" class="form-control" id="contact" required placeholder="e.g. +8801700000000" />
          </div>
          <div class="col-md-6">
            <label for="nationality" class="form-label">Nationality <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="nationality" required placeholder="Country or origin" />
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="designation" class="form-label">Designation <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="designation" required placeholder="e.g. Ambassador, CEO, GM" />
          </div>
          <div class="col-md-6">
            <label for="organization" class="form-label">Organization <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="organization" required placeholder="Company name or Corporate office" />
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-12">
            <label for="profilePhoto" class="form-label">Profile Photo <span class="text-danger">*</span></label>
            <input type="file" class="form-control" id="profilePhoto" accept="image/*,.heic,.heif" required />
            <div class="form-text text-muted">Upload a clear passport-sized photo. Supported formats: JPG, PNG, HEIC (iPhone).</div>
          </div>
        </div>
        <div class="mb-0">
          <label for="mailingAddress" class="form-label">Mailing Address</label>
          <textarea class="form-control" id="mailingAddress" rows="2" placeholder="Full postal address for invites"></textarea>
        </div>
      </div>
