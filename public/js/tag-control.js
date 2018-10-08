$(".tag-enter").click(function(e) {
    e.preventDefault();
    $('#tag-form').removeClass('invisible');
});

$("#tag-cancel").click(function(e) {
    e.preventDefault();
    $('#tag-form').addClass('invisible');
});


$("#tag-add").click(function(e) {
    e.preventDefault();
    var tagName = $('#tagname').val();
    var tagType = $('#tagtype').val();
    var jobId = $(this).data('job-id');
    $('#tag-form').addClass('invisible');

    var data = {
        id: jobId,
        name: tagName,
        type: tagType
    };

    console.log(data);

//     $.ajax({
//         type: "POST",
//         data: JSON.stringify(data),
//         processData: false,
//         contentType : 'application/json',
//         dataType: 'json',
//         url: "/web/configurations",
//         success: function(result) {
//             location.reload();
//         },
//         error: function(result) {
//             $(id+"-help").text('Error!');
//             $(id+"-help").removeClass('invisible');
//         }
//     });
});

