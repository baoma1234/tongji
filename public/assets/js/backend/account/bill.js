define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    function ymToMonthInput(ym) {
        ym = String(ym || '');
        if (ym.length !== 6) return '';
        return ym.slice(0, 4) + '-' + ym.slice(4, 6);
    }

    function monthInputToYm(val) {
        if (!val) return '';
        return val.replace('-', '');
    }

    var Controller = {
        index: function () {
            var billYm = Config.billYm || '';
            $('#bill-ym').val(ymToMonthInput(billYm));

            function buildUrl() {
                var ym = monthInputToYm($('#bill-ym').val()) || billYm;
                return 'account/bill/index?bill_ym=' + ym;
            }

            Table.api.init({
                extend: {
                    index_url: buildUrl(),
                    table: 'acc_bill',
                }
            });

            var table = $("#table");
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                sortOrder: 'desc',
                columns: [[
                    {field: 'id', title: __('Id')},
                    {field: 'user_id', title: __('User_id')},
                    {field: 'category_id', title: __('Category_id')},
                    {field: 'param_name', title: __('Param_name'), operate: 'LIKE'},
                    {field: 'quantity', title: __('Quantity')},
                    {field: 'unit_price', title: __('Unit_price')},
                    {field: 'amount', title: __('Amount'), operate: 'BETWEEN'},
                    {field: 'bill_date', title: __('Bill_date'), operate: 'RANGE', addclass: 'datetimerange'},
                    {field: 'batch_id', title: __('Batch_id'), operate: 'LIKE'},
                    {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime}
                ]]
            });

            $('.btn-search').on('click', function () {
                table.bootstrapTable('refresh', {url: buildUrl()});
            });

            Table.api.bindevent(table);
        }
    };
    return Controller;
});
