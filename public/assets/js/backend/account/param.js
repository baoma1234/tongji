define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'account/param/index' + location.search,
                    add_url: 'account/param/add',
                    edit_url: 'account/param/edit',
                    del_url: 'account/param/del',
                    multi_url: 'account/param/multi',
                    table: 'acc_param',
                }
            });

            var table = $("#table");
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                sortOrder: 'desc',
                columns: [[
                    {checkbox: true},
                    {field: 'id', title: __('Id')},
                    {field: 'category_id', title: __('Category_id'), searchList: Config.categoryList, formatter: function (value) {
                        return Config.categoryList[value] || value;
                    }},
                    {field: 'name', title: __('Name'), operate: 'LIKE'},
                    {field: 'default_price', title: __('Default_price'), operate: 'BETWEEN'},
                    {field: 'unit', title: __('Unit'), operate: 'LIKE'},
                    {field: 'weigh', title: __('Weigh')},
                    {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"0":__('Status 0')}, formatter: Table.api.formatter.status},
                    {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                    {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                ]]
            });
            Table.api.bindevent(table);
        },
        add: function () { Controller.api.bindevent(); },
        edit: function () { Controller.api.bindevent(); },
        api: { bindevent: function () { Form.api.bindevent($("form[role=form]")); } }
    };
    return Controller;
});
