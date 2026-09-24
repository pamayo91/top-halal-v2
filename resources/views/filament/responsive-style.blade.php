<style id="fi-admin-responsive">
    .fi-main { max-width: 100%; min-width: 0; }
    .fi-main-ctn, .fi-page, .fi-section, .fi-ta { min-width: 0; }
    .restaurant-hours-slots.fi-fo-repeater { --restaurant-hours-fields-width: 26rem; --restaurant-hours-action-width: 10.5rem; position: relative; display: block; width: fit-content; max-width: 100%; }
    .restaurant-hours-slots .fi-fo-repeater-items { display: grid; grid-template-columns: max-content; gap: .25rem; width: max-content; }
    .restaurant-hours-slots .fi-fo-repeater-item { display: grid; grid-template-columns: var(--restaurant-hours-fields-width) var(--restaurant-hours-action-width); align-items: center; column-gap: .5rem; border-radius: 0; background: transparent; box-shadow: none; }
    .restaurant-hours-slots .fi-fo-repeater-item-content { grid-column: 1; grid-row: 1; padding: 0; width: var(--restaurant-hours-fields-width); }
    .restaurant-hours-slots .fi-fo-repeater-item-has-header > .fi-fo-repeater-item-content { border-top: 0; padding: 0; }
    .restaurant-hours-slots .fi-fo-repeater-item-header { position: static; grid-column: 2; grid-row: 1; align-self: center; justify-content: flex-start; padding: 0; width: var(--restaurant-hours-action-width); }
    .restaurant-hours-slots .fi-fo-repeater-item-header-end-actions { gap: 0; margin-left: .375rem; }
    .restaurant-hours-slots .fi-fo-field, .restaurant-hours-slots .fi-fo-field-wrp { gap: 0; }
    .restaurant-hours-slots .fi-sc { display: grid; grid-template-columns: max-content max-content; gap: .5rem; width: max-content; }
    .restaurant-hours-slots .fi-input-wrp { min-height: 2rem; width: max-content; }
    .restaurant-hours-slots .fi-input { min-height: 2rem; width: 7.25rem; padding: .25rem .5rem; font-size: .8125rem; }
    .restaurant-hours-slots .fi-input-wrp-prefix { gap: .25rem; padding-inline: .5rem; font-size: .75rem; }
    .restaurant-hours-slots .fi-fo-repeater-add { position: absolute; top: 0; left: calc(var(--restaurant-hours-fields-width) + .5rem); width: var(--restaurant-hours-action-width); justify-content: flex-start; margin: 0; }
    .restaurant-hours-slots .fi-fo-repeater-add .fi-btn { white-space: nowrap; }
    .menu-editor { max-width: 1180px; }
    .menu-editor__intro { display:flex; justify-content:space-between; gap:1rem; align-items:center; margin-bottom:1rem; }
    .menu-editor__intro h2 { margin:0; font-size:1.2rem; font-weight:700; }.menu-editor__intro p { margin:.3rem 0 0; color:var(--gray-500); }
    .menu-editor__tree { border:1px solid var(--gray-200); border-radius:.75rem; background:var(--gray-50); padding:.5rem; min-height:5rem; }
    .menu-editor__row { display:grid; grid-template-columns:1.25rem minmax(10rem,1.15fr) minmax(12rem,1fr) auto auto auto; align-items:center; gap:.6rem; min-height:3rem; margin-left:calc(var(--menu-depth) * 1.5rem); padding:.45rem .6rem; border:1px solid var(--gray-200); border-radius:.55rem; background:white; }
    .menu-editor__branch + .menu-editor__branch { margin-top:.35rem; }.menu-editor__branch .menu-editor__branch { margin-top:.35rem; }
    .menu-editor__handle { color:var(--gray-400); cursor:grab; font-size:1.1rem; }.menu-editor__destination { overflow:hidden; color:var(--gray-500); text-overflow:ellipsis; white-space:nowrap; }.menu-editor__visibility { color:var(--gray-500); white-space:nowrap; }.menu-editor__actions { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:.25rem; }.menu-editor__actions form { display:contents; }.menu-editor__actions button { border:0; border-radius:.35rem; background:transparent; padding:.25rem .35rem; color:var(--primary-600); font:inherit; font-size:.78rem; cursor:pointer; }.menu-editor__actions button:hover { background:var(--primary-50); }.menu-editor__actions button:disabled { color:var(--gray-400); cursor:not-allowed; }.menu-editor__actions .menu-editor__delete { color:var(--danger-600); }.menu-editor__empty { padding:1rem; color:var(--gray-500); }
    .menu-editor__backdrop { position:fixed; z-index:49; inset:0; background:rgb(0 0 0 / .35); }.menu-editor__modal { position:fixed; z-index:50; top:50%; left:50%; width:min(38rem,calc(100vw - 2rem)); max-height:calc(100vh - 2rem); overflow:auto; transform:translate(-50%,-50%); border-radius:.8rem; background:white; box-shadow:0 20px 55px rgb(0 0 0 / .28); }.menu-editor__modal form { display:grid; gap:1rem; padding:1.25rem; }.menu-editor__modal header,.menu-editor__modal footer { display:flex; align-items:center; justify-content:space-between; gap:1rem; }.menu-editor__modal h2,.menu-editor__modal p { margin:0; }.menu-editor__modal header p { color:var(--gray-500); font-size:.875rem; }.menu-editor__modal label { display:grid; gap:.35rem; font-weight:600; }.menu-editor__modal input[type=text],.menu-editor__modal input[type=url],.menu-editor__modal select { width:100%; border:1px solid var(--gray-300); border-radius:.45rem; padding:.55rem .65rem; }.menu-editor__close { border:0; background:transparent; color:var(--gray-500); font-size:1.6rem; cursor:pointer; }.menu-editor__toggles { display:flex; flex-wrap:wrap; gap:1rem; }.menu-editor__toggles label { display:block; font-size:.875rem; }.menu-editor__modal details { border-top:1px solid var(--gray-200); padding-top:.75rem; }.menu-editor__modal details .menu-editor__toggles { margin-top:.7rem; }.menu-editor__error { color:var(--danger-600)!important; font-size:.8rem; }

    @media (max-width: 767px) {
        .fi-main { padding-inline: .75rem; }
        .fi-page-header-main-ctn, .fi-ta-header-ctn, .fi-ta-header-toolbar, .fi-ta-header-toolbar-ctn { min-width: 0; flex-wrap: wrap; }
        .fi-ta-header-toolbar-actions, .fi-ta-header-toolbar .fi-input-wrp { width: 100%; max-width: none; }
        .fi-ta-content { max-width: 100%; }
        .menu-editor__intro { align-items:flex-start; flex-direction:column; }.menu-editor__row { grid-template-columns:1rem minmax(0,1fr) auto; }.menu-editor__destination { grid-column:2; }.menu-editor__visibility { grid-column:3; grid-row:2; }.menu-editor__actions { grid-column:1 / -1; justify-content:flex-start; }.menu-editor__row { margin-left:calc(var(--menu-depth) * .75rem); }
    }
</style>
