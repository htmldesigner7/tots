@push('modals')
<dialog class="modal" id="media-modal" aria-labelledby="media-modal-title">
  <div class="modal__head"><h2 id="media-modal-title">Choose an image</h2><button type="button" class="linkbtn" data-close aria-label="Close">✕</button></div>
  <div class="modal__tools">
    <input type="search" placeholder="Search by name or alt text" data-picker-search aria-label="Search images">
    <select data-picker-folder aria-label="Folder"><option value="">All folders</option></select>
    <label class="btn sm primary upload" data-picker-upload-wrap>Upload new <input type="file" accept="image/*,.svg" multiple data-picker-upload hidden></label>
  </div>
  <p class="small muted" data-picker-status></p>
  <div class="picker" data-picker-grid></div>
  <button type="button" class="btn ghost wide" data-picker-more hidden>Load more</button>
</dialog>
<dialog class="modal modal--sm" id="section-modal" aria-labelledby="section-modal-title">
  <div class="modal__head"><h2 id="section-modal-title">Add a section</h2><button type="button" class="linkbtn" data-close aria-label="Close">✕</button></div>
  <input type="search" placeholder="Filter sections" data-section-filter aria-label="Filter sections">
  <div class="seclib" data-section-lib></div>
</dialog>
@endpush
