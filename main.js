let open = document.querySelector(".employee");
let close = document.querySelector("#close");
let modal = document.querySelector(".modal");
function showBlock() {
  modal.classList.add("b-show");
}
open.addEventListener("click", showBlock);
close.onclick = function () {
  modal.classList.remove("b-show");
};
document.getElementById("myInput").onkeyup = function myFunction() {
  var input, filter, table, tr, td, i, txtValue;
  input = document.getElementById("myInput");
  filter = input.value.toUpperCase();
  table = document.getElementById("tabledata");
  tr = table.getElementsByTagName("tr");
  for (i = 0; i < tr.length; i++) {
    td = tr[i].getElementsByTagName("td")[0];
    if (td) {
      txtValue = td.textContent || td.innerText;
      if (txtValue.toUpperCase().indexOf(filter) > -1) {
        tr[i].style.display = "";
      } else {
        tr[i].style.display = "none";
      }
    }
  }
};
