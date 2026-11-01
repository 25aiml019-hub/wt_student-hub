import java.util.Scanner;

/** Prints the longest word in a line of text and its length. */
public class Main {
    public static void main(String[] args) {
        Scanner scanner = new Scanner(System.in);
        System.out.print("Enter a line: ");
        String line = scanner.nextLine().trim();

        String longestWord = "";
        for (String word : line.split("\\s+")) {
            if (word.length() > longestWord.length()) {
                longestWord = word;
            }
        }
        System.out.println("The longest word is: " + longestWord);
        System.out.println("Its length is: " + longestWord.length());
    }
}
