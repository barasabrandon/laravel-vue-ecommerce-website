<template>
    <div>
        <hr />
        <button
            class="btn btn-warning text-center"
            v-on:click.prevent="addProductToCart()"
        >
            Add To Cart
        </button>
    </div>
</template>

<script>
export default {
    data() {
        return {};
    },
    props: ["productId", "userId"],
    methods: {
        async addProductToCart() {
            // Checking if user Logged in
            if (this.userId == 0) {
                this.$toastr.e(
                    "You need to Login, To add this product to Cart"
                );
                return;
            }

            //If user logged in then add item to cart

            let response = await axios.post("/cart", {
                product_id: this.productId,
            });

            this.$root.$emit("changeInCart", response.data.items); //items from CartsController
        },
    },
    mounted() {
        console.log("Component mounted.");
    },
};
</script>
