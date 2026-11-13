if (document.querySelector('#operator-prototype')) {
    import('./prototype/operator.js');
}
if (document.querySelector('.account-app')) {
    // workspace.js lebih dulu agar dialog konfirmasi menangkap submit sebelum accounts.js.
    import('./workspace.js').then(() => import('./accounts.js'));
}
if (document.querySelector('.proposal-workspace')) {
    import('./proposals.js');
}
