import DataTable from 'datatables.net';

// Compatibility shim: DataTables Buttons (2.2.x+) calls
// DataTable.ext.features.register() which does not exist in the core 2.x
// line (core only exposes DataTable.feature.register). Without this patch,
// importing the buttons extension throws and breaks the whole app.js bundle.
if (DataTable.ext.features && !DataTable.ext.features.register) {
    DataTable.ext.features.register = (name, cb) => {
        DataTable.ext.features[name] = cb;
    };
}

export default DataTable;
