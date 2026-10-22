import { useCallback, useEffect, useRef, useState } from 'react';
import { ImagePlus, Images, Package, Pencil, Plus, Trash2, X } from 'lucide-react';
import api, { equipmentImageUrl } from '../../services/api';
import Modal from '../../components/Modal';
import SearchBar from '../../components/SearchBar';
import { useAuth } from '../../context/AuthContext';
import { useConfirm } from '../../context/ConfirmContext';
import useDebouncedValue from '../../hooks/useDebouncedValue';
import useRealtimeResource from '../../hooks/useRealtimeResource';

const emptyForm = { category_id: '', name: '', description: '', quantity: 1, condition: 'Good', pickup_location: '', min_borrowing_days: '', max_borrowing_days: '' };
const MAX_GALLERY = 10;

export default function Equipment() {
  const { can } = useAuth();
  const confirm = useConfirm();
  const [items, setItems] = useState([]);
  const [cats, setCats] = useState([]);
  const [editing, setEditing] = useState(null); // null = closed, 'new' = adding, object = editing
  const [form, setForm] = useState(emptyForm);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [search, setSearch] = useState('');
  const [catFilter, setCatFilter] = useState('All');
  const [condFilter, setCondFilter] = useState('All');
  const [statusFilter, setStatusFilter] = useState('All');

  // Main profile picture & additional gallery pictures state
  const [mainImageFile, setMainImageFile] = useState(null);
  const [mainImagePreview, setMainImagePreview] = useState(null);
  const [removeMainImage, setRemoveMainImage] = useState(false);
  const [existingImages, setExistingImages] = useState([]);
  const [galleryFiles, setGalleryFiles] = useState([]); // [{ file, preview }]
  const [previewItem, setPreviewItem] = useState(null);
  const [activeSlide, setActiveSlide] = useState(0);

  const mainInputRef = useRef(null);
  const galleryInputRef = useRef(null);

  const load = useCallback(() => api.get('/equipment').then(r => {
    setItems(r.data.equipment);
    setCats(r.data.categories);
  }), []);
  useEffect(() => { load(); }, [load]);

  // Live stock: approvals and returns elsewhere move availability counters.
  useRealtimeResource(['equipment', 'borrowings', 'returns'], load);

  /* ---- Search & filters ---- */
  const query = useDebouncedValue(search.trim(), 300).toLowerCase();
  const filtered = items.filter(x =>
    (!query || x.name.toLowerCase().includes(query) || (x.description || '').toLowerCase().includes(query) || (x.pickup_location || '').toLowerCase().includes(query) || (x.category?.name || '').toLowerCase().includes(query)) &&
    (catFilter === 'All' || String(x.category_id) === catFilter) &&
    (condFilter === 'All' || x.condition === condFilter) &&
    (statusFilter === 'All' || x.status === statusFilter)
  );
  const hasFilters = query !== '' || catFilter !== 'All' || condFilter !== 'All' || statusFilter !== 'All';
  const clearFilters = () => { setSearch(''); setCatFilter('All'); setCondFilter('All'); setStatusFilter('All'); };

  const resetMediaState = () => {
    if (mainImagePreview && mainImagePreview.startsWith('blob:')) {
      URL.revokeObjectURL(mainImagePreview);
    }
    galleryFiles.forEach(g => {
      if (g.preview && g.preview.startsWith('blob:')) URL.revokeObjectURL(g.preview);
    });
    setMainImageFile(null);
    setMainImagePreview(null);
    setRemoveMainImage(false);
    setExistingImages([]);
    setGalleryFiles([]);
  };

  const openNew = () => {
    setError('');
    setForm(emptyForm);
    resetMediaState();
    setEditing('new');
  };

  const openEdit = x => {
    setError('');
    resetMediaState();
    setForm({
      category_id: String(x.category_id),
      name: x.name,
      description: x.description || '',
      quantity: x.quantity,
      condition: x.condition,
      pickup_location: x.pickup_location || '',
      min_borrowing_days: x.min_borrowing_days ?? '',
      max_borrowing_days: x.max_borrowing_days ?? '',
    });
    setMainImagePreview(x.image ? equipmentImageUrl(x.image) : null);
    setExistingImages(Array.isArray(x.images) ? x.images : []);
    setEditing(x);
  };

  const close = () => {
    resetMediaState();
    setEditing(null);
    setForm(emptyForm);
    setError('');
  };

  const handleMainImageSelect = e => {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;
    if (mainImagePreview && mainImagePreview.startsWith('blob:')) {
      URL.revokeObjectURL(mainImagePreview);
    }
    setMainImageFile(file);
    setMainImagePreview(URL.createObjectURL(file));
    setRemoveMainImage(false);
  };

  const handleRemoveMainImage = () => {
    if (mainImagePreview && mainImagePreview.startsWith('blob:')) {
      URL.revokeObjectURL(mainImagePreview);
    }
    setMainImageFile(null);
    setMainImagePreview(null);
    setRemoveMainImage(true);
  };

  const handleGallerySelect = e => {
    const files = Array.from(e.target.files || []);
    e.target.value = '';
    if (!files.length) return;
    const remainingSlots = Math.max(0, MAX_GALLERY - existingImages.length - galleryFiles.length);
    const allowed = files.slice(0, remainingSlots);
    const mapped = allowed.map(file => ({
      file,
      preview: URL.createObjectURL(file),
    }));
    setGalleryFiles(prev => [...prev, ...mapped]);
  };

  const removeExistingGalleryImage = path => {
    setExistingImages(prev => prev.filter(p => p !== path));
  };

  const removeNewGalleryImage = idx => {
    setGalleryFiles(prev => {
      const target = prev[idx];
      if (target?.preview?.startsWith('blob:')) URL.revokeObjectURL(target.preview);
      return prev.filter((_, i) => i !== idx);
    });
  };

  const save = async e => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      const fd = new FormData();
      fd.append('category_id', form.category_id);
      fd.append('name', form.name);
      fd.append('description', form.description || '');
      fd.append('quantity', String(Number(form.quantity)));
      fd.append('condition', form.condition);
      fd.append('pickup_location', form.pickup_location.trim());
      fd.append('min_borrowing_days', form.min_borrowing_days);
      fd.append('max_borrowing_days', form.max_borrowing_days);

      if (mainImageFile) {
        fd.append('image', mainImageFile);
      } else if (removeMainImage) {
        fd.append('remove_image', '1');
      }

      if (editing !== 'new') {
        if (existingImages.length === 0) {
          fd.append('clear_gallery', '1');
        } else {
          existingImages.forEach(p => fd.append('existing_images[]', p));
        }
      }

      galleryFiles.forEach(g => fd.append('gallery[]', g.file));

      if (editing === 'new') {
        await api.post('/admin/equipment', fd);
      } else {
        await api.post(`/admin/equipment/${editing.id}`, fd);
      }
      close();
      load();
    } catch (err) {
      setError(err.response?.data?.message || 'Unable to save equipment.');
    } finally {
      setBusy(false);
    }
  };

  const del = async x => {
    if (!await confirm({
      title: 'Delete equipment?',
      message: `Permanently remove "${x.name}"? This cannot be undone.`,
      confirmLabel: 'Delete Equipment',
      tone: 'danger',
    })) return;
    setError('');
    try { await api.delete(`/admin/equipment/${x.id}`); load(); }
    catch (err) { setError(err.response?.data?.message || 'Unable to delete equipment.'); }
  };

  const openGalleryPreview = item => {
    setActiveSlide(0);
    setPreviewItem(item);
  };

  const previewPhotos = previewItem
    ? [
        ...(previewItem.image ? [{ url: equipmentImageUrl(previewItem.image), label: 'Main Profile Picture' }] : []),
        ...((Array.isArray(previewItem.images) ? previewItem.images : []).map((p, i) => ({
          url: equipmentImageUrl(p),
          label: `Description Photo ${i + 1}`,
        }))),
      ]
    : [];

  const totalGalleryCount = existingImages.length + galleryFiles.length;

  return <>
    <div className="page-heading">
      <div>
        <h1>Equipment Management</h1>
        <p>Add, update, or remove college equipment and its visual photos.</p>
      </div>
      {can('equipment', 'create') && (
        <button className="btn btn-primary" onClick={openNew}>
          <Plus size={16} /> Add Equipment
        </button>
      )}
    </div>
    {error && !editing && <div className="alert error">{error}</div>}

    {/* ---- Search & filters ---- */}
    <div className="toolbar">
      <SearchBar value={search} onChange={setSearch} placeholder="Search by name, description, or category..." />
      <select value={catFilter} onChange={e => setCatFilter(e.target.value)} aria-label="Filter by category">
        <option value="All">All Categories</option>
        {cats.map(c => <option key={c.id} value={String(c.id)}>{c.name}</option>)}
      </select>
      <select value={condFilter} onChange={e => setCondFilter(e.target.value)} aria-label="Filter by condition">
        <option value="All">All Conditions</option>
        <option>Good</option>
        <option>Fair</option>
        <option>Damaged</option>
      </select>
      <select value={statusFilter} onChange={e => setStatusFilter(e.target.value)} aria-label="Filter by availability">
        <option value="All">All Availability</option>
        <option>Available</option>
        <option>Unavailable</option>
      </select>
      {hasFilters && <button className="btn btn-secondary small" onClick={clearFilters}>Clear</button>}
    </div>

    <div className="panel table-wrap">
      <table>
        <thead>
          <tr>
            <th>Equipment</th>
            <th>Category</th>
            <th>Photos</th>
            <th>Total</th>
            <th>Available</th>
            <th>Condition</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          {filtered.map(x => {
            const thumb = equipmentImageUrl(x.image);
            const extraCount = Array.isArray(x.images) ? x.images.length : 0;
            const hasAnyPhoto = Boolean(thumb) || extraCount > 0;
            return (
              <tr key={x.id}>
                <td>
                  <div className="equip-row-cell">
                    <button
                      type="button"
                      className={`equip-thumb-btn ${hasAnyPhoto ? 'clickable' : ''}`}
                      onClick={() => hasAnyPhoto && openGalleryPreview(x)}
                      title={hasAnyPhoto ? 'View equipment photos' : 'No photo uploaded'}
                    >
                      {thumb ? (
                        <img src={thumb} alt={x.name} />
                      ) : (
                        <Package size={18} />
                      )}
                    </button>
                    <div>
                      <div className="equip-row-name">{x.name}</div>
                      <div className="equip-row-sub muted">Pickup: {x.pickup_location || 'Not set'}</div>
                      <div className="equip-row-sub muted">
                        Loan duration: {x.min_borrowing_days != null && x.max_borrowing_days != null
                          ? `${x.min_borrowing_days}–${x.max_borrowing_days} days` : 'No duration restriction'}
                      </div>
                      {x.description && (
                        <div className="equip-row-sub muted">{x.description}</div>
                      )}
                    </div>
                  </div>
                </td>
                <td>{x.category?.name}</td>
                <td>
                  {hasAnyPhoto ? (
                    <button
                      type="button"
                      className="equip-photo-pill"
                      onClick={() => openGalleryPreview(x)}
                    >
                      <Images size={13} />
                      <span>{thumb ? 'Main' : '0 Main'}{extraCount > 0 ? ` + ${extraCount}` : ''}</span>
                    </button>
                  ) : (
                    <span className="muted">None</span>
                  )}
                </td>
                <td>{x.quantity}</td>
                <td>{x.available_quantity}</td>
                <td>{x.condition}</td>
                <td>{x.status}</td>
                <td className="action-cell">
                  {can('equipment', 'edit') && (
                    <button className="btn btn-secondary small" title="Edit" onClick={() => openEdit(x)}>
                      <Pencil size={15} />
                    </button>
                  )}
                  {can('equipment', 'delete') && (
                    <button className="btn btn-danger small" title="Delete" onClick={() => del(x)}>
                      <Trash2 size={15} />
                    </button>
                  )}
                  {!can('equipment', 'edit') && !can('equipment', 'delete') && <span className="muted">View only</span>}
                </td>
              </tr>
            );
          })}
          {!filtered.length && (
            <tr className="empty">
              <td colSpan="8">{items.length ? 'No equipment matches your filters.' : 'No equipment registered yet.'}</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>

    {/* ---- Add / Edit Equipment Modal ---- */}
    <Modal open={!!editing} title={editing === 'new' ? 'Add Equipment' : `Edit — ${editing?.name}`} onClose={close}>
      <form onSubmit={save}>
        <div className="modal-body">
          {/* Main Profile Picture Uploader */}
          <div className="equip-media-section">
            <div className="equip-media-label">
              <span>Main Profile Picture</span>
              <small className="muted">Shown as the primary photo on equipment cards and mobile hero header.</small>
            </div>
            <div className="equip-main-upload">
              <div className="equip-main-preview">
                {mainImagePreview ? (
                  <img src={mainImagePreview} alt="Main equipment preview" />
                ) : (
                  <div className="equip-main-placeholder">
                    <Package size={28} />
                    <span>No main photo</span>
                  </div>
                )}
              </div>
              <div className="equip-main-actions">
                <input
                  ref={mainInputRef}
                  type="file"
                  accept="image/*"
                  hidden
                  onChange={handleMainImageSelect}
                />
                <button
                  type="button"
                  className="btn btn-secondary small"
                  onClick={() => mainInputRef.current?.click()}
                >
                  <ImagePlus size={15} /> {mainImagePreview ? 'Change Main Picture' : 'Upload Main Picture'}
                </button>
                {mainImagePreview && (
                  <button
                    type="button"
                    className="btn btn-danger small"
                    onClick={handleRemoveMainImage}
                  >
                    <Trash2 size={14} /> Remove
                  </button>
                )}
              </div>
            </div>
          </div>

          <label>Category
            <select value={form.category_id} onChange={e => setForm({ ...form, category_id: e.target.value })} required>
              <option value="">Select</option>
              {cats.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
          </label>
          <label>Name
            <input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} required />
          </label>
          <label>Description
            <textarea value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} placeholder="Describe specifications, included accessories, or usage notes..." />
          </label>
          <label>School pickup location
            <input name="pickup_location" value={form.pickup_location} onChange={e => setForm({ ...form, pickup_location: e.target.value })} maxLength={255} required={editing === 'new'} placeholder="e.g. AV Office, Building A, Room 102" />
          </label>
          <div className="form-grid">
            <label>Minimum borrowing days
              <input name="min_borrowing_days" type="number" min="1" max={form.max_borrowing_days || 3650} step="1" value={form.min_borrowing_days} onChange={e => setForm({ ...form, min_borrowing_days: e.target.value })} required={form.max_borrowing_days !== ''} placeholder="e.g. 10" />
            </label>
            <label>Maximum borrowing days
              <input name="max_borrowing_days" type="number" min={form.min_borrowing_days || 1} max="3650" step="1" value={form.max_borrowing_days} onChange={e => setForm({ ...form, max_borrowing_days: e.target.value })} required={form.min_borrowing_days !== ''} placeholder="e.g. 15" />
            </label>
          </div>
          <p className="muted">For 10–15 days, students must choose a return date 10 to 15 days after their borrowing date. Leave both fields empty for no duration restriction.</p>

          {/* Additional Description / Gallery Pictures Uploader */}
          <div className="equip-media-section">
            <div className="equip-media-label">
              <div className="row-between">
                <span>Additional Description Pictures</span>
                <span className="equip-media-counter">{totalGalleryCount} / {MAX_GALLERY}</span>
              </div>
              <small className="muted">Add more angles or close-up photos so students can inspect the equipment in the mobile app.</small>
            </div>

            <input
              ref={galleryInputRef}
              type="file"
              accept="image/*"
              multiple
              hidden
              onChange={handleGallerySelect}
            />

            <div className="equip-gallery-grid">
              {existingImages.map(path => (
                <div key={path} className="equip-gallery-item">
                  <img src={equipmentImageUrl(path)} alt="Equipment gallery" />
                  <button
                    type="button"
                    className="equip-gallery-remove"
                    title="Remove photo"
                    onClick={() => removeExistingGalleryImage(path)}
                  >
                    <X size={13} />
                  </button>
                </div>
              ))}
              {galleryFiles.map((g, idx) => (
                <div key={g.preview} className="equip-gallery-item is-new">
                  <img src={g.preview} alt={`New upload ${idx + 1}`} />
                  <span className="equip-gallery-badge">New</span>
                  <button
                    type="button"
                    className="equip-gallery-remove"
                    title="Remove photo"
                    onClick={() => removeNewGalleryImage(idx)}
                  >
                    <X size={13} />
                  </button>
                </div>
              ))}
              {totalGalleryCount < MAX_GALLERY && (
                <button
                  type="button"
                  className="equip-gallery-add"
                  onClick={() => galleryInputRef.current?.click()}
                >
                  <ImagePlus size={20} />
                  <span>Add Photos</span>
                </button>
              )}
            </div>
          </div>

          <div className="form-grid">
            <label>Quantity
              <input type="number" min="0" value={form.quantity} onChange={e => setForm({ ...form, quantity: e.target.value })} required />
            </label>
            <label>Condition
              <select value={form.condition} onChange={e => setForm({ ...form, condition: e.target.value })}>
                <option>Good</option>
                <option>Fair</option>
                <option>Damaged</option>
              </select>
            </label>
          </div>
          {editing && editing !== 'new' && editing.available_quantity < editing.quantity && (
            <p className="muted small-note">
              {editing.quantity - editing.available_quantity} unit(s) are currently borrowed; total quantity cannot go below that.
            </p>
          )}
          {error && <div className="alert error">{error}</div>}
        </div>
        <div className="modal-actions">
          <button type="button" className="btn btn-secondary" onClick={close}>Cancel</button>
          <button className="btn btn-primary" disabled={busy}>{busy ? 'Saving...' : 'Save Equipment'}</button>
        </div>
      </form>
    </Modal>

    {/* ---- Photo Preview Modal ---- */}
    <Modal
      open={!!previewItem}
      title={previewItem ? `${previewItem.name} — Photos` : 'Photos'}
      onClose={() => setPreviewItem(null)}
    >
      <div className="modal-body">
        {previewPhotos.length > 0 ? (
          <div className="equip-lightbox">
            <div className="equip-lightbox-stage">
              <img
                src={previewPhotos[activeSlide]?.url}
                alt={previewPhotos[activeSlide]?.label || 'Equipment'}
              />
              <span className="equip-lightbox-caption">
                {previewPhotos[activeSlide]?.label} ({activeSlide + 1} / {previewPhotos.length})
              </span>
            </div>
            {previewPhotos.length > 1 && (
              <div className="equip-lightbox-strip">
                {previewPhotos.map((p, idx) => (
                  <button
                    key={idx}
                    type="button"
                    className={`equip-lightbox-thumb ${idx === activeSlide ? 'active' : ''}`}
                    onClick={() => setActiveSlide(idx)}
                  >
                    <img src={p.url} alt={p.label} />
                  </button>
                ))}
              </div>
            )}
          </div>
        ) : (
          <p className="muted">No photos uploaded for this equipment.</p>
        )}
      </div>
      <div className="modal-actions">
        <button type="button" className="btn btn-secondary" onClick={() => setPreviewItem(null)}>Close</button>
      </div>
    </Modal>
  </>;
}
