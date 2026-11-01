if (document.querySelector('#operator-prototype')) {
    import('./prototype/operator.js');
}
if (document.querySelector('.account-app')) {
    import('./accounts.js');
}
if (document.querySelector('.proposal-workspace')) {
    import('./proposals.js');
}
