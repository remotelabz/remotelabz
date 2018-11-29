/**
 * This file implements JavaScript for users/
 */

/**
 * Creates a Bootstrap 3 customized alert.
 * @param {string} type 
 * @param {string} message 
 */
function Notify(type, message) {
var html = '<div class="flash-notice alert alert-' + type + ' alert-dismissible fade show">' +
    '	<button aria-label="Close" class="close" data-dismiss="alert" type="dismiss">' +
    '		<span aria-hidden="true">&times;</span>' +
    '	</button>' +
    message +
'</div>'

$('.flashbag-container').append(html);
}

$(function () {
    var userTable = $('#datatable-checkbox').DataTable({
        dom: "<'row'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'f>>" +
        "<'row'<'col-sm-12'tr>>" +
        "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        order: [
            [1, "asc"]
        ],
        select: 'single',
        buttons: {
            dom: {
                button: {
                    tag: 'button',
                    className: 'btn'
                }
            },
            buttons: [{
                extend: 'selectedSingle',
                text: '<i class="fas fa-user-edit"></i> Edit',
                className: 'btn-primary'
            }, {
                extend: 'selected',
                text: '<i class="fas fa-user-lock"></i> (Un)lock',
                className: 'btn-warning',
                action: toggleUser
            }, {
                extend: 'selected',
                text: '<i class="fas fa-user-times"></i> Delete',
                className: 'btn-danger user-delete',
                action: deleteUser
            }]
        },
        ajax: {
            url: Routing.generate('get_users'),
            dataSrc: 'users'
        },
        columns: [{
                data: 'enabled',
                render: function(data) {
                  return data === true ? '<i class="fas fa-check-square"></i>' : '<i class="fas fa-square"></i>'
                }
            }, {
                data: 'last_name'
            },
            {
                data: 'first_name'
            },
            {
                data: 'email'
            },
            {
                data: 'swarms[, ].name',
                defaultContent: ''
            }
        ],
        createdRow: function (row, data, dataIndex) {
            $(row).data('id', data['id']);
      
            if (!data.enabled) {
                // $(row).addClass('danger')
            }
        }
      });
      
      /* function toggleLoading(button) {
      $(button).prop('disabled', function(i, v) { return !v; })
        .html('<i class="fa fa-circle-o-notch fa-spin"></i>');
      }	*/
      
      // Toggle user request
      function toggleUser(e, dt, node, config) {
        $.ajax({
              url: Routing.generate('toggle_user', {
                  id: $('table tr.selected').data('id')
              }),
              method: 'PATCH',
              contentType: 'application/json'
          })
          .done(function (data, status) {
              userTable.ajax.reload();
      
              Notify('success', data.message);
          })
          .fail(function (data, status) {
              Notify('danger', data.message);
          });
      };
      
      // Delete user request
      function deleteUser() {
        $.ajax({
              url: Routing.generate('delete_user', {
                  id: $('table tr.selected').data('id')
              }),
              method: 'DELETE',
              contentType: 'application/json'
          })
          .done(function (data, status) {
              userTable.ajax.reload();
      
              Notify('success', data.message);
          })
          .fail(function (data, status) {
              Notify('danger', data.message);
          });
      };
      
      /* Handle role change request */
      $('.change-user-role-send').click(function () {
        var token = $(this).data('csrfToken');
        var role = $('select.change-user-role option:checked').val();
        var users = [];
      
        $('#datatables-checkbox tr.selected').each(function (index) {
            users.push($(this).closest('tr').data("userId"));
        });
      
        $.post({
                url: '',
                data: JSON.stringify({
                    users: users,
                    role: role,
                    token: token
                }),
                contentType: 'application/json'
            })
            .done(function (data, status) {
                userTable.ajax.reload();
      
                Notify(data);
            })
      });
      
})
